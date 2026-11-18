<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Shipping\Models\OrderCost;

/**
 * Keeps a private product-cost snapshot per order for profit reporting.
 * It lives outside the Commerce order tables because customers can read those.
 */
class OrderCostService
{
    public function ensure(Order $order): OrderCost
    {
        $cost = OrderCost::query()->firstOrNew(['order_id' => $order->id], ['workspace_id' => $order->workspace_id, 'other_costs' => 0]);
        if ($cost->exists && $cost->is_complete) {
            return $cost;
        }

        $order->loadMissing('items');
        $precision = ShippingMoney::precision($order->currency ?: 'USD');
        $costs = ProductVariant::query()->whereIn('id', $order->items->pluck('variant_id')->filter()->unique())->pluck('cost_price', 'id');

        $lines = [];
        $complete = $order->items->isNotEmpty();
        foreach ($order->items as $item) {
            $unitCost = $item->variant_id ? $costs->get($item->variant_id) : null;
            if ($unitCost === null) {
                $complete = false;

                continue;
            }
            $lines[] = [
                'order_item_id' => $item->id,
                'quantity' => (int) $item->quantity,
                'unit_cost' => ShippingMoney::round($unitCost, $precision),
                'line_cost' => ShippingMoney::convert($unitCost, (int) $item->quantity, $precision),
            ];
        }

        $cost->fill([
            'workspace_id' => $order->workspace_id,
            'product_cost' => ShippingMoney::sum(array_column($lines, 'line_cost'), $precision),
            'is_complete' => $complete,
            'items' => $lines,
        ])->save();

        return $cost;
    }
}
