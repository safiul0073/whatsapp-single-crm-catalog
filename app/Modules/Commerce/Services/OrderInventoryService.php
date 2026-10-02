<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderInventoryService
{
    public function reserve(Order $order): void
    {
        foreach ($order->items->groupBy('variant_id')->sortKeys() as $variantId => $items) {
            $variant = ProductVariant::query()->where('workspace_id', $order->workspace_id)->lockForUpdate()->find($variantId);
            if (! $variant || $variant->status !== 'active') {
                throw ValidationException::withMessages(['stock' => 'An order variant is no longer available.']);
            }
            $quantity = $items->sum('quantity');
            $held = OrderReservation::query()->where('variant_id', $variantId)->where('order_id', '!=', $order->id)
                ->where('state', 'reserved')->where('expires_at', '>', now())->lockForUpdate()->get()->sum('quantity');
            if ($variant->stock_quantity - $held < $quantity) {
                throw ValidationException::withMessages(['stock' => "Insufficient available stock for {$variant->sku}."]);
            }
            $existing = OrderReservation::query()->where('order_id', $order->id)->where('variant_id', $variantId)->first();
            $retained = $existing && $existing->state === 'reserved' && $existing->expires_at->isFuture();
            $generation = $existing ? $existing->generation + ($retained ? 0 : 1) : 1;
            $reservation = OrderReservation::query()->updateOrCreate(['order_id' => $order->id, 'variant_id' => $variantId], [
                'quantity' => $quantity, 'state' => 'reserved', 'generation' => $generation,
                'expires_at' => $retained ? $existing->expires_at : now()->addHours(StoreOrderSetting::forWorkspace($order->workspace_id)->reservation_hours),
                'operation_key' => "order:{$order->id}:reserve:{$variantId}",
            ]);
            $this->recordReservation($order, $reservation, 'reserved');
        }
    }

    public function pay(Order $order): void
    {
        if ($order->inventory_adjusted_at) {
            return;
        }
        $this->reserve($order);
        foreach ($order->reservations()->orderBy('variant_id')->get() as $reservation) {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($reservation->variant_id);
            $variant->decrement('stock_quantity', $reservation->quantity);
            $reservation->update(['state' => 'consumed']);
            InventoryMovement::query()->firstOrCreate(['idempotency_key' => "order:{$order->id}:paid:{$variant->id}"], [
                'workspace_id' => $order->workspace_id, 'variant_id' => $variant->id, 'order_id' => $order->id,
                'quantity_delta' => -$reservation->quantity, 'reason' => 'order_paid',
            ]);
        }
        $order->inventory_adjusted_at = now();
    }

    public function release(Order $order): void
    {
        foreach ($order->reservations()->orderBy('variant_id')->get() as $reservation) {
            ProductVariant::query()->lockForUpdate()->findOrFail($reservation->variant_id);
            if ($reservation->state === 'consumed' && ! $order->shipped_at) {
                $movement = InventoryMovement::query()->firstOrCreate(['idempotency_key' => "order:{$order->id}:cancelled:{$reservation->variant_id}"], [
                    'workspace_id' => $order->workspace_id, 'variant_id' => $reservation->variant_id, 'order_id' => $order->id,
                    'quantity_delta' => $reservation->quantity, 'reason' => 'order_cancelled',
                ]);
                if ($movement->wasRecentlyCreated) {
                    ProductVariant::query()->whereKey($reservation->variant_id)->increment('stock_quantity', $reservation->quantity);
                }
            }
            $this->recordReservation($order, $reservation, 'released');
            $reservation->update(['state' => 'released']);
        }
        $order->inventory_restored_at = now();
    }

    private function recordReservation(Order $order, OrderReservation $reservation, string $action): void
    {
        InventoryMovement::query()->firstOrCreate(['idempotency_key' => "order:{$order->id}:reservation:{$reservation->variant_id}:{$reservation->generation}:{$action}"], [
            'workspace_id' => $order->workspace_id, 'variant_id' => $reservation->variant_id, 'order_id' => $order->id, 'quantity_delta' => 0,
            'reason' => 'reservation_'.$action, 'metadata' => ['quantity' => $reservation->quantity, 'expires_at' => $reservation->expires_at->toIso8601String()],
        ]);
    }

    public function expire(): int
    {
        $count = 0;
        foreach (OrderReservation::query()->where('state', 'reserved')->where('expires_at', '<=', now())->distinct()->pluck('order_id') as $id) {
            DB::transaction(function () use ($id, &$count): void {
                $order = Order::query()->lockForUpdate()->findOrFail($id);
                $reservations = $order->reservations()->where('state', 'reserved')->where('expires_at', '<=', now())->get();
                foreach ($reservations as $reservation) {
                    $this->recordReservation($order, $reservation, 'expired');
                    $reservation->update(['state' => 'expired']);
                }
                $expired = $reservations->count();
                if ($expired) {
                    app(UnifiedOrderService::class)->event($order, 'reservation_expired', 'Stock reservation expired');
                    $count++;
                }
            });
        }

        return $count;
    }
}
