<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Storefront\Models\ProductReview;

/**
 * Keeps a product's rating and reviews_count equal to its approved reviews.
 */
class ProductRatingService
{
    public function refresh(int $workspaceId, int $productId): void
    {
        $product = Product::query()->where('workspace_id', $workspaceId)->find($productId);
        if ($product === null) {
            return;
        }

        $stats = ProductReview::query()
            ->where('workspace_id', $workspaceId)
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        $count = (int) ($stats?->total ?? 0);
        $product->rating = $count > 0 ? round((float) $stats->average, 2) : 0;
        $product->reviews_count = $count;

        if ($product->isDirty(['rating', 'reviews_count'])) {
            $product->save();
        }
    }

    /**
     * Recalculates every product (optionally for one workspace). Returns the number of products checked.
     */
    public function refreshAll(?int $workspaceId = null): int
    {
        $checked = 0;
        Product::query()
            ->when($workspaceId !== null, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->select(['id', 'workspace_id'])
            ->chunkById(200, function ($products) use (&$checked): void {
                foreach ($products as $product) {
                    $this->refresh((int) $product->workspace_id, (int) $product->id);
                    $checked++;
                }
            });

        return $checked;
    }
}
