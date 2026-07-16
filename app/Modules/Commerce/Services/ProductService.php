<?php

namespace App\Modules\Commerce\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Models\Audience;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductOption;
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
            $this->syncOptions($product, $data['options'] ?? []);
            $this->syncVariants($product, $data['variants'] ?? []);
            $this->audit->log($product, 'created');

            return $this->loadProduct($product);
        });
    }

    public function createDraft(int $workspaceId, array $data): Product
    {
        return DB::transaction(function () use ($workspaceId, $data): Product {
            $product = Product::query()->create($this->productData($workspaceId, $data) + ['status' => 'draft', 'wizard_step' => 2]);
            $this->audit->log($product, 'created');

            return $this->loadProduct($product);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update($this->productData($product->workspace_id, $data, $product));
            $this->syncOptions($product, $data['options'] ?? []);
            $this->syncVariants($product, $data['variants'] ?? []);
            $this->audit->log($product, 'updated');

            return $this->loadProduct($product);
        });
    }

    public function updateDetails(Product $product, array $data): Product
    {
        $product->update($this->productData($product->workspace_id, $data, $product) + ['wizard_step' => max(2, $product->wizard_step)]);
        $this->audit->log($product, 'updated');

        return $this->loadProduct($product);
    }

    public function updateGallery(Product $product, array $items): Product
    {
        return DB::transaction(function () use ($product, $items): Product {
            $media = Media::query()->whereIn('id', collect($items)->pluck('id'))->get()->keyBy('id');
            $retained = [];
            $primaryMediaId = null;

            foreach (array_values($items) as $position => $item) {
                $record = $media->get((int) $item['id']);
                if (! $record) {
                    throw ValidationException::withMessages(['media' => 'One or more gallery files no longer exist.']);
                }

                $galleryItem = ProductMedia::query()->updateOrCreate(
                    ['product_id' => $product->id, 'media_id' => $record->id],
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
                if ($galleryItem->is_primary) {
                    $primaryMediaId = $record->id;
                }
            }

            $product->gallery()->whereNotIn('id', $retained ?: [0])->delete();
            $product->update(['primary_media_id' => $primaryMediaId, 'wizard_step' => max(3, $product->wizard_step)]);
            $this->audit->logCustom('commerce.product.gallery_updated', ['product_id' => $product->id, 'media_ids' => collect($items)->pluck('id')->all()]);

            return $this->loadProduct($product);
        });
    }

    public function updateOptions(Product $product, array $options): Product
    {
        return DB::transaction(function () use ($product, $options): Product {
            $this->syncOptions($product, $options);
            $product->update(['wizard_step' => max(4, $product->wizard_step)]);
            $this->audit->logCustom('commerce.product.options_updated', ['product_id' => $product->id]);

            return $this->loadProduct($product);
        });
    }

    public function variantPreview(Product $product): array
    {
        $product->loadMissing(['options.values', 'variants']);
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

            return [
                'id' => $existing?->id,
                'attributes' => $attributes,
                'sku' => $existing?->sku ?? Str::limit($generated, 120, ''),
                'meta_retailer_id' => $existing?->meta_retailer_id ?? Str::limit($generated, 120, ''),
                'media_id' => $existing?->media_id,
                'price' => $existing?->price,
                'compare_at_price' => $existing?->compare_at_price,
                'stock_quantity' => $existing?->stock_quantity ?? 0,
                'weight_kg' => $existing?->weight_kg,
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
                $option->values()->create(['workspace_id' => $product->workspace_id, 'value' => $value, 'position' => $valuePosition]);
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

            $variant = ProductVariant::query()
                ->where('workspace_id', $product->workspace_id)
                ->where('product_id', $product->id)
                ->when(filled($variantData['id'] ?? null), fn ($query) => $query->whereKey($variantData['id']), fn ($query) => $query->where('sku', $variantData['sku']))
                ->firstOrNew();
            $variant->fill([
                'workspace_id' => $product->workspace_id,
                'product_id' => $product->id,
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
            'brand' => $brandName,
            'description' => $data['description'] ?? null,
            'care_information' => $data['care_information'] ?? null,
            'condition' => $data['condition'] ?? 'new',
            'audience' => $audienceName,
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
        return $product->load(['category', 'primaryMedia', 'gallery.media', 'options.values', 'variants.media']);
    }
}
