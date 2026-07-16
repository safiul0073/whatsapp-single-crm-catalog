<?php

namespace App\Modules\Commerce\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    private const TRANSITIONS = [
        'requested' => ['needs_details', 'quoted', 'cancelled'],
        'needs_details' => ['requested', 'quoted', 'cancelled'],
        'quoted' => ['awaiting_payment', 'cancelled'],
        'awaiting_payment' => ['paid', 'cancelled'],
        'paid' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(protected AuditLogService $audit) {}

    public function quote(Order $order, array $data): Order
    {
        if (! in_array($order->status, ['requested', 'needs_details', 'quoted', 'awaiting_payment'], true)) {
            throw ValidationException::withMessages(['order' => "Order cannot be quoted while {$order->status}."]);
        }
        if (($order->issues ?? []) !== []) {
            throw ValidationException::withMessages(['order' => 'Resolve catalog issues before quoting this order.']);
        }
        $order->forceFill([
            'shipping_address' => $data['shipping_address'],
            'shipping_amount' => $data['shipping_amount'],
            'total' => (float) $order->subtotal + (float) $data['shipping_amount'],
            'delivery_method' => $data['delivery_method'] ?? null,
            'delivery_notes' => $data['delivery_notes'] ?? null,
            'duties_disclosure' => $data['duties_disclosure'] ?? 'Import duties and taxes, if any, are the buyer’s responsibility unless stated otherwise.',
            'payment_url' => $data['payment_url'] ?? null,
            'status' => filled($data['payment_url'] ?? null) ? 'awaiting_payment' : 'quoted',
        ])->save();
        $this->audit->logCustom('commerce.order.quoted', ['order_id' => $order->id, 'number' => $order->number]);

        return $order->fresh('items');
    }

    public function transition(Order $order, string $to, array $data = []): Order
    {
        if (! in_array($to, self::TRANSITIONS[$order->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Order cannot move from {$order->status} to {$to}."]);
        }

        return DB::transaction(function () use ($order, $to, $data): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($to === 'paid') {
                $this->deductInventory($locked);
                $locked->paid_at = now();
            }
            if ($to === 'cancelled' && $locked->inventory_adjusted_at && ! $locked->shipped_at) {
                $this->restoreInventory($locked);
            }
            if ($to === 'shipped') {
                $locked->tracking_number = $data['tracking_number'] ?? null;
                $locked->tracking_url = $data['tracking_url'] ?? null;
                $locked->shipped_at = now();
            }
            $locked->status = $to;
            $locked->save();
            $this->audit->logCustom('commerce.order.status_changed', ['order_id' => $locked->id, 'status' => $to]);

            return $locked->fresh('items');
        });
    }

    protected function deductInventory(Order $order): void
    {
        if ($order->inventory_adjusted_at) {
            return;
        }
        foreach ($order->items as $item) {
            if (! $item->variant_id) {
                throw ValidationException::withMessages(['inventory' => "Order item {$item->retailer_id} is not linked to inventory."]);
            }
            $variant = ProductVariant::query()->whereKey($item->variant_id)->lockForUpdate()->firstOrFail();
            if ($variant->stock_quantity < $item->quantity) {
                throw ValidationException::withMessages(['inventory' => "Insufficient stock for {$variant->sku}."]);
            }
            $variant->decrement('stock_quantity', $item->quantity);
            InventoryMovement::query()->firstOrCreate(
                ['idempotency_key' => "order:{$order->id}:paid:{$variant->id}"],
                ['workspace_id' => $order->workspace_id, 'variant_id' => $variant->id, 'order_id' => $order->id, 'quantity_delta' => -$item->quantity, 'reason' => 'order_paid']
            );
        }
        $order->inventory_adjusted_at = now();
    }

    protected function restoreInventory(Order $order): void
    {
        if ($order->inventory_restored_at) {
            return;
        }
        foreach ($order->items as $item) {
            if (! $item->variant_id) {
                continue;
            }
            $variant = ProductVariant::query()->whereKey($item->variant_id)->lockForUpdate()->firstOrFail();
            $movement = InventoryMovement::query()->firstOrCreate(
                ['idempotency_key' => "order:{$order->id}:cancelled:{$variant->id}"],
                ['workspace_id' => $order->workspace_id, 'variant_id' => $variant->id, 'order_id' => $order->id, 'quantity_delta' => $item->quantity, 'reason' => 'order_cancelled']
            );
            if ($movement->wasRecentlyCreated) {
                $variant->increment('stock_quantity', $item->quantity);
            }
        }
        $order->inventory_restored_at = now();
    }
}
