<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use Illuminate\Support\Carbon;
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

    /**
     * Releases the stock held for variants an edited order no longer contains.
     */
    public function releaseRemoved(Order $order): void
    {
        $variantIds = $order->items->pluck('variant_id')->unique()->all();
        foreach ($order->reservations()->where('state', 'reserved')->whereNotIn('variant_id', $variantIds)->orderBy('variant_id')->get() as $reservation) {
            $this->recordReservation($order, $reservation, 'released');
            $reservation->update(['state' => 'released']);
        }
    }

    /**
     * Moves stock for a paid order whose lines were edited: takes out what was added and puts back what was removed.
     *
     * @param  array<int, int>  $previousQuantities  variant id => quantity before the edit
     */
    public function adjustConsumed(Order $order, array $previousQuantities, int $editNumber): void
    {
        $currentQuantities = $order->items->groupBy('variant_id')->map(fn ($items): int => (int) $items->sum('quantity'))->all();
        $variantIds = collect(array_keys($previousQuantities))->merge(array_keys($currentQuantities))->unique()->sort()->values();
        foreach ($variantIds as $variantId) {
            $delta = ($currentQuantities[$variantId] ?? 0) - ($previousQuantities[$variantId] ?? 0);
            $variant = ProductVariant::query()->where('workspace_id', $order->workspace_id)->lockForUpdate()->find($variantId);
            if (! $variant) {
                continue;
            }
            if ($delta > 0) {
                $held = OrderReservation::query()->where('variant_id', $variantId)->where('order_id', '!=', $order->id)
                    ->where('state', 'reserved')->where('expires_at', '>', now())->lockForUpdate()->get()->sum('quantity');
                if ($variant->status !== 'active' || $variant->stock_quantity - $held < $delta) {
                    throw ValidationException::withMessages(['stock' => "Insufficient available stock for {$variant->sku}."]);
                }
            }
            if ($delta !== 0) {
                InventoryMovement::query()->firstOrCreate(['idempotency_key' => "order:{$order->id}:edit:{$editNumber}:{$variantId}"], [
                    'workspace_id' => $order->workspace_id, 'variant_id' => $variantId, 'order_id' => $order->id,
                    'quantity_delta' => -$delta, 'reason' => 'order_edited',
                ]);
                $delta > 0 ? $variant->decrement('stock_quantity', $delta) : $variant->increment('stock_quantity', -$delta);
            }
            $quantity = $currentQuantities[$variantId] ?? 0;
            $existing = OrderReservation::query()->where('order_id', $order->id)->where('variant_id', $variantId)->first();
            if ($quantity > 0) {
                OrderReservation::query()->updateOrCreate(['order_id' => $order->id, 'variant_id' => $variantId], [
                    'quantity' => $quantity, 'state' => 'consumed', 'generation' => $existing?->generation ?? 1,
                    'expires_at' => $existing?->expires_at ?? now(), 'operation_key' => "order:{$order->id}:reserve:{$variantId}",
                ]);
            } elseif ($existing) {
                $existing->update(['state' => 'released']);
            }
        }
    }

    private function recordReservation(Order $order, OrderReservation $reservation, string $action): void
    {
        InventoryMovement::query()->firstOrCreate(['idempotency_key' => "order:{$order->id}:reservation:{$reservation->variant_id}:{$reservation->generation}:{$action}"], [
            'workspace_id' => $order->workspace_id, 'variant_id' => $reservation->variant_id, 'order_id' => $order->id, 'quantity_delta' => 0,
            'reason' => 'reservation_'.$action, 'metadata' => ['quantity' => $reservation->quantity, 'expires_at' => $reservation->expires_at->toIso8601String()],
        ]);
    }

    /**
     * The moment the stock held for an unpaid order is released.
     */
    public function holdExpiresAt(Order $order): ?Carbon
    {
        $expiresAt = $order->reservations()->where('state', 'reserved')->min('expires_at');

        return $expiresAt ? Carbon::parse($expiresAt) : null;
    }

    /**
     * Gives an unpaid order more time before its held stock is released.
     */
    public function extendHold(Order $order, int $hours): Carbon
    {
        return $this->holdUntil($order, null, $hours);
    }

    /**
     * Moves the hold deadline to an exact moment, or adds hours to the current deadline.
     */
    public function holdUntil(Order $order, ?Carbon $until, int $hours = 0): Carbon
    {
        return DB::transaction(function () use ($order, $until, $hours): Carbon {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'awaiting_payment' || $locked->payment_state === 'paid') {
                throw ValidationException::withMessages(['hours' => 'Only unpaid orders awaiting payment can have their hold extended.']);
            }
            $this->reserve($locked->load('items'));
            $expiresAt = $until ?? ($this->holdExpiresAt($locked) ?? now())->max(now())->addHours($hours);
            $locked->reservations()->where('state', 'reserved')->update(['expires_at' => $expiresAt]);
            app(UnifiedOrderService::class)->event($locked, 'hold_extended_'.$expiresAt->timestamp, 'Stock held until '.$expiresAt->toDayDateTimeString());

            return $expiresAt;
        });
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
                if ($expired && $order->status === 'awaiting_payment' && $order->payment_state !== 'paid' && ! $order->paid_at) {
                    app(UnifiedOrderService::class)->event($order, 'hold_expired', 'Payment window expired');
                    app(OrderWorkflowService::class)->transition($order, 'cancelled');
                }
            });
        }

        return $count;
    }
}
