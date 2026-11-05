<?php

namespace App\Modules\Commerce\AiTools;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\StorefrontUrl;
use App\Modules\Commerce\Services\StoreSalesPolicy;
use App\Modules\Commerce\Storefront\Services\CatalogService;
use App\Modules\Media\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Turns catalog products into the facts a store assistant may tell customers.
 * Prices are filtered by the store's retail/wholesale switches, so a wholesale-only store never exposes retail prices.
 */
class ChatbotProductPresenter
{
    public const MAX_SHOWCASE_COLORS = 4;

    protected ?StoreSalesPolicy $policy = null;

    public function __construct(
        public readonly int $workspaceId,
        protected CatalogService $catalog,
        protected StorefrontUrl $urls,
    ) {}

    public function policy(): StoreSalesPolicy
    {
        return $this->policy ??= StoreSalesPolicy::for($this->workspaceId);
    }

    /**
     * Products a customer can buy from the storefront, already limited by the store's selling mode.
     */
    public function query(): Builder
    {
        return $this->catalog->query($this->workspaceId, ['selling_mode' => 'all'])->with('gallery');
    }

    public function find(string|int $reference): ?Product
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return null;
        }

        $query = $this->query();

        return ctype_digit($reference)
            ? $query->where(fn (Builder $q) => $q->whereKey((int) $reference)->orWhere('slug', $reference))->first()
            : $query->where('slug', $reference)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'url' => $this->urls->product($this->workspaceId, $product->slug),
            'category' => $product->category?->name,
            'colors' => $product->colors->map(fn (ProductColor $color): string => $color->display_name)->values()->all(),
            'prices' => $this->priceLines($product),
            'in_stock' => $this->availability($product)->contains(fn (int $quantity): bool => $quantity > 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function details(Product $product): array
    {
        $available = $this->availability($product);
        $activeVariants = $product->variants->whereIn('status', ['active', 'out_of_stock']);

        $colors = $product->colors->map(function (ProductColor $color) use ($product, $activeVariants, $available): array {
            $variants = $activeVariants->where('color_id', $color->id);

            return [
                'id' => $color->id,
                'name' => $color->display_name,
                'image_url' => $this->colorImage($product, $color),
                'sizes_in_stock' => $variants->filter(fn (ProductVariant $v): bool => ($available[$v->id] ?? 0) > 0)->pluck('size')->filter()->unique()->values()->all(),
                'in_stock' => $variants->contains(fn (ProductVariant $v): bool => ($available[$v->id] ?? 0) > 0),
            ];
        })->values()->all();

        return array_merge($this->summary($product), [
            'short_description' => $product->short_description,
            'material' => $product->material,
            'sizes' => $activeVariants->pluck('size')->filter()->unique()->values()->all(),
            'colors' => $colors,
            'wholesale' => $this->wholesaleFacts($product),
        ]);
    }

    /**
     * Up to four colors with images, ready to send as chat media.
     *
     * @return array{product_id: int, name: string, slug: string, url: string, colors: list<array{color: string, image_url: string, price_line: ?string}>, more_colors: int}
     */
    public function showcase(Product $product): array
    {
        $priceLine = $this->priceLines($product)[0] ?? null;
        $colors = $product->colors
            ->map(fn (ProductColor $color): array => ['color' => $color->display_name, 'image_url' => $this->colorImage($product, $color), 'price_line' => $priceLine])
            ->filter(fn (array $color): bool => filled($color['image_url']))
            ->values();

        if ($colors->isEmpty() && ($image = $this->absolute($product->primaryMedia?->url))) {
            $colors = collect([['color' => '', 'image_url' => $image, 'price_line' => $priceLine]]);
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'url' => $this->urls->product($this->workspaceId, $product->slug),
            'colors' => $colors->take(self::MAX_SHOWCASE_COLORS)->all(),
            'more_colors' => max(0, $colors->count() - self::MAX_SHOWCASE_COLORS),
        ];
    }

    /**
     * @return list<string>
     */
    public function priceLines(Product $product): array
    {
        $policy = $this->policy();
        $lines = [];

        if ($product->isRetailEnabled() && ($retail = $policy->money($product->single_piece_price ?? $product->variants->where('status', 'active')->min('price')))) {
            $lines[] = "Retail: {$retail} per piece";
        }

        if ($product->isWholesaleEnabled()) {
            if ($product->usesPackPricing()) {
                foreach ($product->packPrices() as $row) {
                    $lines[] = "Wholesale: {$policy->money($row['price'])} per pack when ordering {$row['packs']}+ packs";
                }
            } elseif ($wholesale = $policy->money($product->wholesale_price)) {
                $lines[] = "Wholesale: {$wholesale} per piece (minimum {$this->minimumPieces($product)} pieces)";
            }
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function wholesaleFacts(Product $product): ?array
    {
        if (! $product->isWholesaleEnabled()) {
            return null;
        }

        $multiplier = max(1, (int) ($product->ws_ratio_multiplier ?: 1));
        $boxes = $product->colors->mapWithKeys(function (ProductColor $color) use ($product, $multiplier): array {
            $ratio = (array) ($product->getEffectiveSizeRatios()[$color->id] ?? []);

            return [$color->display_name => collect($ratio)->map(fn ($qty): int => (int) $qty * $multiplier)->filter()->all()];
        })->filter()->all();

        return [
            'order_unit' => $product->usesPackPricing() ? 'packs' : 'pieces',
            'minimum_order' => $product->usesPackPricing() ? $product->minimumPacks().' packs' : $this->minimumPieces($product).' pieces',
            'minimum_per_color' => max(1, (int) ($product->ws_color_moq ?: 1)),
            'minimum_sizes' => $product->ws_min_sizes ? (int) $product->ws_min_sizes : null,
            'one_box_per_color' => $boxes,
            'shipping' => 'Wholesale prices include shipping.',
        ];
    }

    protected function minimumPieces(Product $product): int
    {
        return (int) ($product->ws_main_moq ?: ($product->moq ?: 1));
    }

    /**
     * @return Collection<int, int> available quantity keyed by variant id
     */
    protected function availability(Product $product): Collection
    {
        $variants = $product->variants->whereIn('status', ['active', 'out_of_stock']);
        $reserved = ProductVariant::reservedQuantities($variants->pluck('id'));

        return $variants->mapWithKeys(fn (ProductVariant $v): array => [$v->id => $v->availableQuantity($reserved[$v->id] ?? 0)]);
    }

    protected function colorImage(?Product $product, ProductColor $color): ?string
    {
        $galleryImage = $product?->relationLoaded('gallery')
            ? $product->gallery
                ->where('color_id', $color->id)
                ->filter(fn ($item): bool => $item->media instanceof Media && $item->media->isImage())
                ->sortByDesc('is_primary')
                ->first()?->media?->url
            : null;

        return $this->absolute($galleryImage ?? $color->swatchMedia?->url ?? $product?->primaryMedia?->url);
    }

    protected function absolute(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : url($url);
    }
}
