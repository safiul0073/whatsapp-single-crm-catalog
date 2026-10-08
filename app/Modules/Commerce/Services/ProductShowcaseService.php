<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Shipping\Models\ShippingZoneCountry;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Validation\ValidationException;

class ProductShowcaseService
{
    public function find(Workspace $workspace, string $slug): Product
    {
        abort_unless((bool) data_get($workspace->settings, 'commerce.shop_enabled', true), 404);

        return Product::query()->where('workspace_id', $workspace->id)
            ->where('slug', $slug)->where('status', 'active')->where('visibility', 'published')
            ->with(['primaryMedia', 'gallery.media', 'colors.swatchMedia', 'options.values', 'tierPrices',
                'variants' => fn ($query) => $query->whereIn('status', ['active', 'out_of_stock'])->orderBy('id'),
            ])->firstOrFail();
    }

    public function payload(Product $product): array
    {
        $held = OrderReservation::query()->whereIn('variant_id', $product->variants->modelKeys())
            ->where('state', 'reserved')->where('expires_at', '>', now())
            ->selectRaw('variant_id, SUM(quantity) AS quantity')->groupBy('variant_id')->pluck('quantity', 'variant_id');
        $variants = $product->variants->map(fn ($variant) => [
            'id' => $variant->id, 'color_id' => $variant->color_id,
            'size' => $variant->size ?: ($variant->attributes['size'] ?? 'One size'),
            'sku' => $variant->sku, 'price' => $product->single_piece_price ?? $variant->price,
            'available' => $variant->status === 'active' ? max(0, $variant->stock_quantity - (int) ($held[$variant->id] ?? 0)) : 0,
        ])->values();
        $gallery = $product->gallery->filter(fn ($item) => $item->media && $item->media_type === 'image')->map(fn ($item) => [
            'url' => $item->media->url, 'color_id' => $item->color_id, 'alt' => $item->alt_text ?: $product->name,
        ])->values();
        if ($gallery->isEmpty() && $product->primaryMedia) {
            $gallery->push(['url' => $product->primaryMedia->url, 'color_id' => null, 'alt' => $product->name]);
        }
        $settings = StoreOrderSetting::query()->where('workspace_id', $product->workspace_id)->first();

        return [
            'id' => $product->id, 'slug' => $product->slug, 'name' => $product->name,
            'description' => strip_tags($product->description ?? ''),
            'short_description' => strip_tags($product->short_description ?? ''),
            'specifications' => collect($product->specifications ?? [])->filter(fn ($spec) => is_array($spec) && filled($spec['label'] ?? null) && filled($spec['value'] ?? null))->map(fn ($spec) => ['label' => strip_tags((string) $spec['label']), 'value' => strip_tags((string) $spec['value'])])->values(),
            'details' => array_filter(['Material' => $product->material, 'Fit' => $product->fit, 'Set includes' => $product->set_includes, 'Care' => $product->care_information], fn ($value) => filled($value)),
            'currency' => $settings?->currency ?? StoreOrderSetting::catalogCurrency($product->workspace_id),
            'retail_enabled' => $product->isRetailEnabled(), 'wholesale_enabled' => $product->isWholesaleEnabled(),
            'wholesale_price' => $product->wholesale_price,
            'wholesale' => ['ratios' => (object) $product->getEffectiveSizeRatios(), 'multiplier' => max(1, (int) $product->ws_ratio_multiplier),
                'minimum' => max(1, (int) $product->ws_main_moq), 'color_minimum' => max(1, (int) $product->ws_color_moq), 'min_sizes' => max(1, (int) $product->ws_min_sizes)],
            'tiers' => $product->tierPrices->sortBy('min_quantity')->map(fn ($tier) => ['min' => $tier->min_quantity, 'max' => $tier->max_quantity, 'price' => $tier->unit_price])->values(),
            'gallery' => $gallery,
            'colors' => $product->colors->map(fn ($color) => [
                'id' => $color->id,
                'name' => $this->customerColorName($color->display_name),
                'hex' => $color->hex_code,
                'image' => $color->swatchMedia?->url,
            ])->values(),
            'variants' => $variants, 'total_available' => $variants->sum('available'),
            'countries' => ShippingZoneCountry::query()->where('workspace_id', $product->workspace_id)
                ->whereHas('zone', fn ($query) => $query->where('is_active', true))->orderBy('country_code')->pluck('country_code')->unique()->values(),
        ];
    }

    /**
     * Prices every currently available piece at retail and ships it as one parcel, so the page can show
     * "all stock" value and delivery cost. Without retail pricing only the value is returned.
     *
     * @return array<string, mixed>
     */
    public function stockQuote(Workspace $workspace, Product $product, string $country, ?int $shippingMethodId, UnifiedOrderService $orders): array
    {
        $payload = $this->payload($product);
        $availableVariants = collect($payload['variants'])->filter(fn (array $variant): bool => $variant['available'] > 0);
        $pieces = (int) $availableVariants->sum('available');
        $base = ['pieces' => $pieces, 'currency' => $payload['currency'], 'country' => $country];

        if ($pieces === 0) {
            return $base + ['weight_kg' => 0, 'chargeable_weight_kg' => null, 'subtotal' => '0', 'shipping_amount' => null, 'total' => null, 'shipping_quote_required' => true, 'shipping_options' => [], 'shipping_method_id' => null, 'delivery_method' => null];
        }

        if (! $payload['retail_enabled']) {
            $subtotal = $availableVariants->sum(fn (array $variant): float => $variant['available'] * (float) ($payload['wholesale_price'] ?? $variant['price']));

            return $base + ['weight_kg' => null, 'chargeable_weight_kg' => null, 'subtotal' => number_format($subtotal, 2, '.', ''), 'shipping_amount' => null, 'total' => null, 'shipping_quote_required' => true, 'shipping_options' => [], 'shipping_method_id' => null, 'delivery_method' => null];
        }

        $request = [
            'groups' => $availableVariants->map(fn (array $variant): array => ['mode' => 'retail', 'product_id' => $product->id, 'variant_id' => $variant['id'], 'quantity' => $variant['available']])->values()->all(),
            'shipping_address' => ['country' => $country],
        ];

        try {
            $preview = $orders->preview($workspace, $request + ['shipping_method_id' => $shippingMethodId]);
        } catch (ValidationException) {
            $preview = $orders->preview($workspace, $request);
        }

        $physicalWeight = data_get($preview, 'weight.total_physical_weight_kg');

        return $base + collect($preview)->only(['currency', 'subtotal', 'shipping_amount', 'total', 'shipping_quote_required', 'shipping_options', 'shipping_method_id', 'delivery_method'])->all() + [
            'weight_kg' => $physicalWeight !== null ? round((float) $physicalWeight, 3) : null,
            'chargeable_weight_kg' => $preview['chargeable_weight_kg'] !== null ? round((float) $preview['chargeable_weight_kg'], 3) : null,
        ];
    }

    /**
     * Some catalogs store names like "Black (#0A0A0A)"; customers only see the color name.
     */
    protected function customerColorName(string $name): string
    {
        return trim((string) preg_replace('/\s*\(\s*#?[0-9a-f]{3,8}\s*\)\s*$/i', '', $name)) ?: $name;
    }
}
