<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderBox;
use App\Modules\Commerce\Models\OrderBoxItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPackingService
{
    public function addRetailBox(Order $order, array $quantities): OrderBox
    {
        return DB::transaction(function () use ($order, $quantities): OrderBox {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->source === 'pos') {
                app(PosService::class)->assertCanFulfill($locked);
            }
            if (! in_array($locked->status, ['paid', 'processing'], true)) {
                throw ValidationException::withMessages(['packing' => 'Confirm payment before packing.']);
            }
            $box = $locked->boxes()->create(['kind' => 'retail', 'label' => $locked->number.'-BOX-'.($locked->boxes()->count() + 1)]);
            $count = 0;
            foreach ($quantities as $itemId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity < 1) {
                    continue;
                }
                $item = $locked->items()->findOrFail($itemId);
                $group = $locked->groups()->find($item->group_id);
                $allocated = OrderBoxItem::query()->where('order_item_id', $item->id)->sum('quantity');
                if ($group?->mode === 'wholesale' || $allocated + $quantity > $item->quantity) {
                    throw ValidationException::withMessages(['packing' => 'Box contents exceed unallocated retail quantities.']);
                }
                $box->contents()->create(['order_item_id' => $item->id, 'quantity' => $quantity]);
                $count += $quantity;
            }
            if ($count === 0) {
                throw ValidationException::withMessages(['packing' => 'Add at least one piece to the box.']);
            }

            return $box;
        });
    }

    public function markPacked(Order $order, int $boxId): void
    {
        DB::transaction(function () use ($order, $boxId): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->source === 'pos') {
                app(PosService::class)->assertCanFulfill($locked);
            }
            if (! in_array($locked->status, ['paid', 'processing', 'packed'], true)) {
                throw ValidationException::withMessages(['packing' => 'Confirm payment before packing.']);
            }
            $box = $locked->boxes()->findOrFail($boxId);
            if (! $box->packed_at) {
                $box->update(['packed_at' => now()]);
            }
            if ($this->complete($locked)) {
                $locked->update(['status' => 'packed', 'packed_at' => $locked->packed_at ?? now()]);
                app(UnifiedOrderService::class)->event($locked, 'packed', 'All boxes packed');
            }
        });
    }

    public function complete(Order $order): bool
    {
        if (! $order->boxes()->exists() || $order->boxes()->whereNull('packed_at')->exists()) {
            return false;
        }
        foreach ($order->items as $item) {
            if ((int) OrderBoxItem::query()->where('order_item_id', $item->id)->sum('quantity') !== $item->quantity) {
                return false;
            }
        }

        return true;
    }
}
