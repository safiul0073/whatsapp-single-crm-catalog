<?php

namespace App\Modules\Commerce\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Models\Audience;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductOption;
use App\Modules\Commerce\Models\ProductTierPrice;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Media\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        protected ProductReadinessService $readiness,
        protected AuditLogService $audit,
    ) {}

    public function create(int $workspaceId, array $data): Product
    {
        return DB::transaction(function () use ($workspaceId, $data): Product {
            $product = Product::query()->create($this->productData($workspaceId, $data));
            $this->syncColors($product, $data['colors'] ?? []);
            $this->syncOptions($product, $data['options'] ?? []);
            $this->syncVariants($product, $data['variants'] ?? []);
            $this->syncTierPrices($product, $data['tier_prices'] ?? []);
            $this->audit->log($product, 'created');

            return $this->loadProduct($product);
        });
    }

    public function createDraft(int $workspaceId, array $data): Product
    {
        return DB::transaction(function () use ($workspaceId, $data): Product {
            $product = Product::query()->create($this->productData($workspaceId, $data) + ['status' => 'draft', 'wizard_step' => 2]);
            $this->syncColors($product, $data['colors'] ?? []);
            $this->syncTierPrices($product, $data['tier_prices'] ?? []);
            $this->audit->log($product, 'created');

            return $this->loadProduct($product);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update($this->productData($product->workspace_id, $data, $product));
            $this->syncColors($product, $data['colors'] ?? []);
            $this->syncOptions($product, $data['options'] ?? []);
            $this->syncVariants($product, $data['variants'] ?? []);
            $this->syncTierPrices($product, $data['tier_prices'] ?? []);
            $this->audit->log($product, 'updated');

            return $this->loadProduct($product);
        });
    }

    public function updateDetails(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update($this->productData($product->workspace_id, $data, $product) + ['wizard_step' => max(2, $product->wizard_step)]);
            if (isset($data['colors'])) {
                $this->syncColors($product, $data['colors']);
            }
            if (isset($data['tier_prices'])) {
                $this->syncTierPrices($product, $data['tier_prices']);
            }
            if (isset($data['default_stock'])) {
                $stock = (int) $data['default_stock'];
                if ($product->variants()->exists()) {
                    $product->variants()->update(['stock_quantity' => $stock]);
                } else {
                    $variants = $this->variantPreview($product);
                    if (! empty($variants)) {
                        foreach ($variants as &$v) {
                            $v['stock_quantity'] = $stock;
                        }
                        $this->syncVariants($product, $variants);
                    }
                }
            }
            $this->audit->log($product, 'updated');

            return $this->loadProduct($product);
        });
    }

    public function updateGallery(Product $product, array $items, array $colors = []): Product
    {
        return DB::transaction(function () use ($product, $items, $colors): Product {
            $retained = [];
            $primaryMediaId = null;

            foreach (array_values($items) as $position => $item) {
                $record = Media::query()->where('uploaded_by', $product->workspace->owner_id ?? auth()->id())->whereKey($item['id'])->firstOrFail();
                $colorId = ((int) ($item['color_id'] ?? 0)) > 0 ? (int) $item['color_id'] : null;

                $galleryItem = ProductMedia::query()->updateOrCreate(
                    ['product_id' => $product->id, 'media_id' => $record->id, 'color_id' => $colorId],
                    [
                        'workspace_id' => $product->workspace_id,
                        'media_type' => $record->type,
                        'role' => ($item['is_primary'] ?? false) ? 'primary' : 'gallery',
                        'alt_text' => $item['alt_text'] ?? null,
                        'position' => $position,
                        'is_primary' => (bool) ($item['is_primary'] ?? false),
                    ]
                );
                $retained[] = $galleryItem->id;
                if ($galleryItem->is_primary && $record->type === 'image') {
                    $primaryMediaId = $record->id;
                }
            }

            if (! empty($colors)) {
                $this->syncColors($product, $colors);
            }

            $product->gallery()->whereNotIn('id', $retained ?: [0])->delete();

            // Set overall primary media fallback
            if (! $primaryMediaId) {
                $firstImage = $product->gallery()->where('media_type', 'image')->orderBy('position')->first();
                $primaryMediaId = $firstImage?->media_id;
                if ($firstImage) {
                    $firstImage->update(['is_primary' => true]);
                }
            }

            // Sync each color's swatch_media_id from its dedicated gallery
            foreach ($product->colors()->get() as $color) {
                $colorPrimary = $color->gallery()->where('is_primary', true)->first()?->media_id
                    ?? $color->gallery()->first()?->media_id;
                if ($colorPrimary) {
                    $color->update(['swatch_media_id' => $colorPrimary]);
                }
            }

            $product->update(['primary_media_id' => $primaryMediaId, 'wizard_step' => max(4, $product->wizard_step)]);
            $this->audit->logCustom('commerce.product.gallery_updated', ['product_id' => $product->id, 'media_ids' => collect($items)->pluck('id')->all()]);

            return $this->loadProduct($product);
        });
    }

    public function updateOptions(Product $product, array $options): Product
    {
        return DB::transaction(function () use ($product, $options): Product {
            $this->syncOptions($product, $options);
            $product->update(['wizard_step' => max(3, $product->wizard_step)]);
            $this->audit->logCustom('commerce.product.options_updated', ['product_id' => $product->id]);

            return $this->loadProduct($product);
        });
    }

    public function variantPreview(Product $product): array
    {
        $product->loadMissing(['options.values', 'variants', 'colors']);
        $combinations = [[]];

        foreach ($product->options as $option) {
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($option->values as $value) {
                    $next[] = $combination + [$option->code => $value->value];
                }
            }
            $combinations = $next;
        }

        return collect($combinations)->map(function (array $attributes, int $index) use ($product): array {
            $existing = $product->variants->first(fn (ProductVariant $variant): bool => $this->attributeKey($variant->attributes ?? []) === $this->attributeKey($attributes));
            $suffix = collect($attributes)->map(fn (string $value): string => Str::upper(Str::slug($value, '')))->filter()->implode('-');
            $generated = Str::upper(Str::slug($product->slug, '-')).($suffix ? '-'.$suffix : '-'.($index + 1));

            // Match color if color attribute is present
            $colorId = null;
            $colorMediaId = null;
            if (isset($attributes['color']) && $product->colors->isNotEmpty()) {
                $colorMatch = $product->colors->first(fn (ProductColor $c) => (filled($c->hex_code) && Str::lower($c->hex_code) === Str::lower($attributes['color'])) || (filled($c->name) && Str::lower($c->name) === Str::lower($attributes['color'])));
                $colorId = $colorMatch?->id;
                $colorMediaId = $colorMatch?->swatch_media_id;
            }

            // Lookup size option value for weight
            $sizeWeightKg = null;
            if (isset($attributes['size'])) {
                $sizeOption = $product->options->firstWhere('code', 'size');
                $sizeValue = $sizeOption?->values->firstWhere('value', $attributes['size']);
                if ($sizeValue && $sizeValue->weight !== null) {
                    $w = (float) $sizeValue->weight;
                    $unit = strtolower($sizeValue->weight_unit ?? 'kg');
                    if ($unit === 'g') {
                        $w = $w / 1000;
                    } elseif ($unit === 'lb') {
                        $w = $w * 0.453592;
                    } elseif ($unit === 'oz') {
                        $w = $w * 0.0283495;
                    }
                    $sizeWeightKg = $w;
                }
            }

            return [
                'id' => $existing?->id,
                'color_id' => $existing?->color_id ?? $colorId,
                'size' => $existing?->size ?? ($attributes['size'] ?? null),
                'attributes' => $attributes,
                'sku' => $existing?->sku ?? Str::limit($generated, 120, ''),
                'meta_retailer_id' => $existing?->meta_retailer_id ?? Str::limit($generated, 120, ''),
                'media_id' => $existing?->media_id ?? $colorMediaId,
                'price' => $existing?->price ?? $product->single_piece_price,
                'compare_at_price' => $existing?->compare_at_price,
                'stock_quantity' => $existing?->stock_quantity ?? 0,
                'weight_kg' => $existing?->weight_kg ?? $sizeWeightKg ?? $product->default_unit_weight_kg,
                'package_dimensions' => $existing?->package_dimensions,
                'status' => $existing?->status ?? 'active',
            ];
        })->values()->all();
    }

    public function updateVariants(Product $product, array $variants): Product
    {
        return DB::transaction(function () use ($product, $variants): Product {
            $this->syncVariants($product, $variants);
            $product->update(['wizard_step' => 5]);
            $this->audit->logCustom('commerce.product.variants_updated', ['product_id' => $product->id, 'variant_count' => count($variants)]);

            return $this->loadProduct($product);
        });
    }

    public function publish(Product $product, string $status): Product
    {
        if ($status === 'active') {
            $issues = $this->readiness->issues($product);
            if ($issues !== []) {
                throw ValidationException::withMessages(['status' => collect($issues)->pluck('message')->all()]);
            }
        }

        $product->update(['status' => $status, 'published_at' => $status === 'active' ? ($product->published_at ?? now()) : $product->published_at, 'wizard_step' => 5]);
        $this->audit->logCustom('commerce.product.status_changed', ['product_id' => $product->id, 'status' => $status]);

        return $this->loadProduct($product);
    }

    public function syncColors(Product $product, array $colors): void
    {
        $retained = [];
        foreach (array_values($colors) as $position => $colorData) {
            if (blank($colorData['name'] ?? null) && blank($colorData['hex_code'] ?? null)) {
                continue;
            }

            $color = filled($colorData['id'] ?? null)
                ? ProductColor::query()->where('workspace_id', $product->workspace_id)->where('product_id', $product->id)->whereKey($colorData['id'])->firstOrNew()
                : new ProductColor;

            $color->fill([
                'workspace_id' => $product->workspace_id,
                'product_id' => $product->id,
                'swatch_media_id' => $colorData['swatch_media_id'] ?? null,
                'name' => $colorData['name'] ?? null,
                'hex_code' => $colorData['hex_code'] ?? null,
                'color_family' => $colorData['color_family'] ?? null,
                'position' => $position,
            ])->save();

            $retained[] = $color->id;
        }

        if (! empty($retained)) {
            $product->colors()->whereNotIn('id', $retained)->delete();
        }
    }

    public function syncTierPrices(Product $product, array $tierPrices): void
    {
        $retained = [];
        foreach ($tierPrices as $tierData) {
            if (empty($tierData['unit_price']) || (float) $tierData['unit_price'] <= 0) {
                continue;
            }

            $minQty = max(1, (int) ($tierData['min_quantity'] ?? 1));
            $tier = ProductTierPrice::query()
                ->where('workspace_id', $product->workspace_id)
                ->where('product_id', $product->id)
                ->where('min_quantity', $minQty)
                ->firstOrNew();

            $tier->fill([
                'workspace_id' => $product->workspace_id,
                'product_id' => $product->id,
                'min_quantity' => $minQty,
                'max_quantity' => filled($tierData['max_quantity'] ?? null) ? (int) $tierData['max_quantity'] : null,
                'unit_price' => $tierData['unit_price'],
                'discount_percentage' => $tierData['discount_percentage'] ?? null,
            ])->save();

            $retained[] = $tier->id;
        }

        if (! empty($retained)) {
            $product->tierPrices()->whereNotIn('id', $retained)->delete();
        }
    }

    protected function syncOptions(Product $product, array $options): void
    {
        $product->options()->delete();
        foreach ($options as $position => $optionData) {
            if (blank($optionData['name'] ?? null)) {
                continue;
            }

            $option = ProductOption::query()->create([
                'workspace_id' => $product->workspace_id,
                'product_id' => $product->id,
                'name' => $optionData['name'],
                'code' => Str::snake($optionData['code'] ?? $optionData['name']),
                'position' => $position,
            ]);
            foreach (array_values(array_filter($optionData['values'] ?? [])) as $valuePosition => $value) {
                if (is_array($value)) {
                    $option->values()->create([
                        'workspace_id' => $product->workspace_id,
                        'value' => $value['value'],
                        'weight' => $value['weight'] ?? null,
                        'weight_unit' => $value['weight_unit'] ?? 'kg',
                        'position' => $valuePosition,
                    ]);
                } else {
                    $option->values()->create([
                        'workspace_id' => $product->workspace_id,
                        'value' => $value,
                        'position' => $valuePosition,
                    ]);
                }
            }
        }
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        $retained = [];
        foreach ($variants as $variantData) {
            if (blank($variantData['sku'] ?? null)) {
                continue;
            }

            $variant = filled($variantData['id'] ?? null)
                ? ProductVariant::query()->where('workspace_id', $product->workspace_id)->where('product_id', $product->id)->whereKey($variantData['id'])->firstOrNew()
                : (ProductVariant::query()->where('workspace_id', $product->workspace_id)->where('sku', $variantData['sku'])->first() ?: new ProductVariant);

            $variant->fill([
                'workspace_id' => $product->workspace_id,
                'product_id' => $product->id,
                'color_id' => $variantData['color_id'] ?? null,
                'size' => $variantData['size'] ?? ($variantData['attributes']['size'] ?? null),
                'media_id' => $variantData['media_id'] ?? null,
                'sku' => $variantData['sku'],
                'meta_retailer_id' => $variantData['meta_retailer_id'] ?? $variantData['sku'],
                'attributes' => $variantData['attributes'] ?? [],
                'price' => $variantData['price'],
                'compare_at_price' => $variantData['compare_at_price'] ?? null,
                'stock_quantity' => $variantData['stock_quantity'] ?? 0,
                'weight_kg' => $variantData['weight_kg'] ?? null,
                'package_dimensions' => $variantData['package_dimensions'] ?? null,
                'status' => $variantData['status'] ?? 'active',
            ])->save();
            $retained[] = $variant->id;
        }

        $removed = $product->variants()->whereNotIn('id', $retained ?: [0])->get();
        foreach ($removed as $variant) {
            $variant->orderItems()->exists() ? $variant->update(['status' => 'archived']) : $variant->delete();
        }
    }

    protected function productData(int $workspaceId, array $data, ?Product $product = null): array
    {
        $categoryId = filled($data['category_id'] ?? null)
            ? Category::query()->where('workspace_id', $workspaceId)->whereKey($data['category_id'])->value('id')
            : null;
        $brandId = array_key_exists('brand_id', $data) ? $data['brand_id'] : $product?->brand_id;
        $audienceId = array_key_exists('audience_id', $data) ? $data['audience_id'] : $product?->audience_id;
        $brandName = $brandId ? Brand::query()->where('workspace_id', $workspaceId)->whereKey($brandId)->value('name') : ($data['brand'] ?? null);
        $audienceName = $audienceId ? Audience::query()->where('workspace_id', $workspaceId)->whereKey($audienceId)->value('name') : ($data['audience'] ?? null);

        return array_filter([
            'workspace_id' => $workspaceId,
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'audience_id' => $audienceId,
            'primary_media_id' => $data['primary_media_id'] ?? $product?->primary_media_id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($workspaceId, (string) ($data['slug'] ?? $data['name']), $product?->id),
            'sku' => $data['sku'] ?? $product?->sku,
            'visibility' => $data['visibility'] ?? $product?->visibility ?? 'published',
            'brand' => $brandName,
            'short_description' => array_key_exists('short_description', $data) ? $data['short_description'] : $product?->short_description,
            'description' => array_key_exists('description', $data) ? $data['description'] : $product?->description,
            'care_information' => array_key_exists('care_information', $data) ? $data['care_information'] : $product?->care_information,
            'features' => $data['features'] ?? $product?->features,
            'feature_highlights' => $data['feature_highlights'] ?? $product?->feature_highlights,
            'shipping_countries' => $data['shipping_countries'] ?? $product?->shipping_countries,
            'specifications' => $data['specifications'] ?? $product?->specifications,
            'fit' => $data['fit'] ?? $product?->fit ?? 'USA True-to-Size',
            'set_includes' => $data['set_includes'] ?? $product?->set_includes,
            'gender' => $data['gender'] ?? $product?->gender ?? 'Unisex (Boys & Girls)',
            'season' => $data['season'] ?? $product?->season ?? 'All Season',
            'shipping_info' => $data['shipping_info'] ?? $product?->shipping_info ?? 'USA & Canada Shipping',
            'delivery_time' => $data['delivery_time'] ?? $product?->delivery_time ?? '6–10 Working Days Delivery',
            'moq' => array_key_exists('moq', $data) ? (int) $data['moq'] : ($product?->moq ?? 1),
            'rating' => array_key_exists('rating', $data) ? (float) $data['rating'] : ($product?->rating ?? 5.00),
            'reviews_count' => array_key_exists('reviews_count', $data) ? (int) $data['reviews_count'] : ($product?->reviews_count ?? 128),
            'condition' => $data['condition'] ?? 'new',
            'audience' => $audienceName,
            'fabric_gsm' => $data['fabric_gsm'] ?? $product?->fabric_gsm,
            'material' => $data['material'] ?? $product?->material,
            'default_unit_weight_kg' => $data['default_unit_weight_kg'] ?? $product?->default_unit_weight_kg ?? 0.030,
            'single_piece_price' => $data['single_piece_price'] ?? $product?->single_piece_price,
            'wholesale_price' => $data['wholesale_price'] ?? $product?->wholesale_price,
            'country_of_origin' => strtoupper($data['country_of_origin'] ?? 'BD'),
            'status' => $data['status'] ?? $product?->status ?? 'draft',
        ], fn (mixed $value, string $key): bool => $key !== 'primary_media_id' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    protected function uniqueSlug(int $workspaceId, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'product';
        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('workspace_id', $workspaceId)->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function attributeKey(array $attributes): string
    {
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    protected function loadProduct(Product $product): Product
    {
        return $product->load(['category', 'primaryMedia', 'gallery.media', 'options.values', 'colors.swatchMedia', 'tierPrices', 'variants.media', 'variants.color']);
    }
}
