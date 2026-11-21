<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Jobs\NotifyLowStock;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;

class LowStockAlertService
{
    /**
     * Flags variants whose stock has dropped to the store's low-stock threshold and queues one owner alert
     * per product. A variant alerts once per drop; the flag clears when it is restocked above the threshold.
     * Only a falling stock level raises an alert, so creating or restocking variants never notifies.
     */
    public function check(ProductVariant $variant, bool $decreased = true): void
    {
        $settings = StoreOrderSetting::forWorkspace($variant->workspace_id);
        $threshold = max(0, (int) $settings->low_stock_threshold);

        if ((int) $variant->stock_quantity > $threshold) {
            ProductVariant::query()->whereKey($variant->id)->whereNotNull('low_stock_notified_at')->update(['low_stock_notified_at' => null]);

            return;
        }

        if (! $decreased || ! $settings->low_stock_alerts_enabled || $variant->status !== 'active') {
            return;
        }

        $flagged = ProductVariant::query()->whereKey($variant->id)->whereNull('low_stock_notified_at')->update(['low_stock_notified_at' => now()]);

        if ($flagged) {
            NotifyLowStock::dispatch($variant->product_id)->delay(now()->addMinute())->afterCommit();
        }
    }

    /**
     * Clears the low-stock flag on products whose stock an admin set in bulk, skipping model events.
     *
     * @param  iterable<int, int>  $productIds
     */
    public function checkProducts(iterable $productIds): void
    {
        ProductVariant::query()->whereIn('product_id', collect($productIds)->all())->each(fn (ProductVariant $variant) => $this->check($variant, false));
    }
}
