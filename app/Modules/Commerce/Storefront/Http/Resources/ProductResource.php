<?php

namespace App\Modules\Commerce\Storefront\Http\Resources;

use App\Modules\Commerce\Models\ProductType;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\ProductTypeService;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = StoreOrderSetting::catalogCurrency($this->workspace_id);
        $singlePrice = (string) ($this->single_piece_price ?? $this->variants->where('status', 'active')->min('price') ?? '0');
        $wholesaleOn = StoreFeatures::wholesaleEnabled($this->workspace_id);
        $wholesalePrice = $wholesaleOn ? (string) ($this->wholesale_price ?? '0') : '0';
        $sellingMode = $wholesaleOn ? ($this->selling_mode ?? 'both') : 'retail';
        $itemMoq = (int) ($this->ws_main_moq ?: ($this->moq ?: 1));

        $wsSettings = [
            'enabled' => $wholesaleOn && (bool) ($this->ws_enabled ?? false),
            'main_moq' => $itemMoq,
            'color_moq' => (int) ($this->ws_color_moq ?: 1),
            'min_sizes' => $this->ws_min_sizes ? (int) $this->ws_min_sizes : null,
            'size_ratios' => $this->getEffectiveSizeRatios(),
            'ratio_multiplier' => (int) ($this->ws_ratio_multiplier ?: 1),
        ];

        $storefrontSetting = null;
        if ($request) {
            $cacheKey = 'storefront_setting_'.$this->workspace_id;
            if (! $request->attributes->has($cacheKey)) {
                $request->attributes->set($cacheKey, StorefrontSetting::where('workspace_id', $this->workspace_id)->first());
            }
            $storefrontSetting = $request->attributes->get($cacheKey);
        } else {
            $storefrontSetting = StorefrontSetting::where('workspace_id', $this->workspace_id)->first();
        }

        $whatsappNumber = collect([
            $storefrontSetting?->config['contact_phone'] ?? null,
            $storefrontSetting?->config['whatsapp_number'] ?? null,
            $storefrontSetting?->config['phone'] ?? null,
            data_get($storefrontSetting?->config, 'chat_channels.whatsapp.phone'),
        ])->first(fn ($val) => filled($val) && is_string($val));

        $typeKey = 'product_type_'.$this->workspace_id.'_'.($this->category_id ?? 0);
        if (! $request->attributes->has($typeKey)) {
            $request->attributes->set($typeKey, app(ProductTypeService::class)->forCategory($this->category, $this->workspace_id));
        }
        /** @var ProductType $type */
        $type = $request->attributes->get($typeKey);
        $values = $this->attribute_values ?? [];

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $singlePrice,
            'single_piece_price' => $singlePrice,
            'wholesale_price' => $wholesalePrice,
            'selling_mode' => $sellingMode,
            'moq' => $itemMoq,
            'ws_main_moq' => $itemMoq,
            'ws_color_moq' => (int) ($this->ws_color_moq ?: 1),
            'ws_min_sizes' => $this->ws_min_sizes ? (int) $this->ws_min_sizes : null,
            'ws_enabled' => $wholesaleOn && (bool) ($this->ws_enabled ?? false),
            'wholesale_settings' => $wsSettings,
            'currency' => $currency,
            'image' => $this->primaryMedia?->url,
            'primary_image' => $this->primaryMedia?->url,
            'category' => $this->category?->only(['id', 'name', 'slug']),
            'product_type' => ['slug' => $type->slug, 'name' => $type->name, 'layout' => $type->layout, 'variant_axes' => $type->variant_axes ?? []],
            'attributes' => collect($type->attributes ?? [])
                ->filter(fn ($a) => array_key_exists($a['key'], $values))
                ->map(fn ($a) => ['key' => $a['key'], 'label' => $a['label'], 'value' => $values[$a['key']], 'show_on_card' => (bool) ($a['show_on_card'] ?? false)])
                ->values(),
            'brand' => $this->brandRecord?->only(['id', 'name', 'slug']),
            'gallery' => $this->whenLoaded('gallery', fn () => $this->gallery->map(fn ($g) => [
                'url' => $g->media?->url,
                'type' => $g->media_type,
                'color_id' => $g->color_id,
            ])),
            'variants' => $this->variants->whereIn('status', ['active', 'out_of_stock'])->values()->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'size' => $v->size,
                'color_id' => $v->color_id,
                'attributes' => (object) ($v->attributes ?? []),
                'price' => (string) ($this->single_piece_price ?? $v->price),
                'stock' => $v->status === 'active' ? $v->stock_quantity : 0,
                'image' => $v->image,
            ]),
            'colors' => $this->colors->map(function ($c) {
                $colorImg = $c->swatchMedia?->url
                    ?? $this->variants->firstWhere('color_id', $c->id)?->image
                    ?? ($this->relationLoaded('gallery') ? $this->gallery->firstWhere('color_id', $c->id)?->media?->url : null);

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'hex' => $c->hex_code,
                    'hex_code' => $c->hex_code,
                    'swatch_image' => $colorImg,
                ];
            }),
            'options' => $this->options->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'code' => $o->code,
                'values' => $o->relationLoaded('values') ? $o->values->pluck('value')->all() : [],
            ]),
            'tier_prices' => collect($wholesaleOn ? $this->tierPrices : [])->map(fn ($t) => [
                'id' => $t->id,
                'min_quantity' => (int) $t->min_quantity,
                'max_quantity' => $t->max_quantity ? (int) $t->max_quantity : null,
                'price' => (float) $t->price,
                'discount_percentage' => (int) $t->discount_percentage,
            ]),
            'feature_highlights' => $this->feature_highlights ?? [],
            'specifications' => $this->specifications ?? [],
            'condition' => $this->condition ?? 'new',
            'delivery_time' => $this->delivery_time ?? '6-10 Working Days',
            'shipping_info' => $this->shipping_info ?? 'USA & Canada',
            'care_information' => $this->care_information,
            'features' => $this->features ?? [],
            'rating' => (float) ($this->rating ?? 5.0),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'whatsapp_number' => $whatsappNumber,
            'shipping_estimate' => [
                'country' => 'US',
                'currency' => $currency,
                'available_methods' => [
                    ['id' => 'standard', 'name' => 'Standard Air Freight', 'cost' => 10.00, 'formatted_cost' => '10.00', 'estimated_delivery' => '6-10 Working Days'],
                    ['id' => 'home_delivery', 'name' => 'Home Delivery', 'cost' => 15.00, 'formatted_cost' => '15.00', 'estimated_delivery' => '3-5 Working Days'],
                ],
            ],
        ];
    }
}
