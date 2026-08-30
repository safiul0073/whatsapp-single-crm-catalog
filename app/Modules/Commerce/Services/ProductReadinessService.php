<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Product;

class ProductReadinessService
{
    public function issues(Product $product): array
    {
        $product->loadMissing(['primaryMedia', 'gallery.media', 'variants', 'options.values']);
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

        // Wholesale settings validation
        if ($product->isWholesaleEnabled()) {
            $sizeOption = $product->options->first(fn ($o) => strtolower($o->code) === 'size' || strtolower($o->name) === 'size');
            $totalSizes = $sizeOption ? $sizeOption->values->count() : 0;

            if ($product->ws_min_sizes !== null && $totalSizes > 0 && $product->ws_min_sizes > $totalSizes) {
                $issues[] = ['code' => 'ws_min_sizes_exceeds', 'message' => "Wholesale minimum sizes ({$product->ws_min_sizes}) cannot exceed total available sizes ({$totalSizes})."];
            }

            if ($product->ws_main_moq < 1) {
                $issues[] = ['code' => 'ws_main_moq_invalid', 'message' => 'Wholesale main MOQ must be at least 1.'];
            }

            if (!empty($product->ws_size_ratios) && $sizeOption) {
                $sizeValues = $sizeOption->values->pluck('value')->all();
                foreach ($product->ws_size_ratios as $colorId => $ratios) {
                    $ratioKeys = array_keys($ratios);
                    $mismatched = array_diff($ratioKeys, $sizeValues);
                    if (!empty($mismatched)) {
                        $issues[] = ['code' => 'ws_ratios_mismatch', 'message' => "Wholesale size ratios for color '{$colorId}' contain sizes not defined in the product: " . implode(', ', $mismatched)];
                    }
                }
            }
        }

        return $issues;
    }

    public function isReady(Product $product): bool
    {
        return $this->issues($product) === [];
    }
}
