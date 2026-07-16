<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Product;

class ProductReadinessService
{
    public function issues(Product $product): array
    {
        $product->loadMissing(['primaryMedia', 'gallery.media', 'variants']);
        $issues = [];

        if (! $product->primaryMedia || $product->primaryMedia->type !== 'image') {
            $issues[] = ['code' => 'missing_primary_image', 'message' => 'Choose a primary catalog image.'];
        } elseif (! str_starts_with($product->primaryMedia->url, 'https://')) {
            $issues[] = ['code' => 'image_not_https', 'message' => 'The primary image must use a public HTTPS URL.'];
        }

        if ($product->variants->isEmpty()) {
            $issues[] = ['code' => 'missing_variants', 'message' => 'Generate at least one sellable variant.'];
        }

        if (! $product->variants->contains(fn ($variant): bool => in_array($variant->status, ['active', 'out_of_stock'], true))) {
            $issues[] = ['code' => 'unavailable_variants', 'message' => 'Keep at least one active or intentionally out-of-stock variant.'];
        }

        if ($product->variants->pluck('sku')->filter()->duplicates()->isNotEmpty()) {
            $issues[] = ['code' => 'duplicate_sku', 'message' => 'Variant SKUs must be unique.'];
        }

        if ($product->variants->pluck('meta_retailer_id')->filter()->duplicates()->isNotEmpty()) {
            $issues[] = ['code' => 'duplicate_retailer_id', 'message' => 'Meta retailer IDs must be unique.'];
        }

        return $issues;
    }

    public function isReady(Product $product): bool
    {
        return $this->issues($product) === [];
    }
}
