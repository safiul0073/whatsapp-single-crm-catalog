<?php

namespace App\Modules\Commerce\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Mail\OrderShippedMail;
use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    private const TRANSITIONS = [
        'draft' => ['requested', 'awaiting_payment', 'cancelled'],
        'requested' => ['needs_details', 'quoted', 'cancelled'],
        'needs_details' => ['requested', 'quoted', 'cancelled'],
        'quoted' => ['awaiting_payment', 'cancelled'],
        'awaiting_payment' => ['paid', 'cancelled'],
        'paid' => ['processing', 'cancelled'],
        'processing' => ['packed', 'cancelled'],
        'packed' => ['shipped', 'cancelled'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(protected AuditLogService $audit) {}

    public function quote(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, ['requested', 'needs_details', 'quoted', 'awaiting_payment'], true)) {
                throw ValidationException::withMessages(['order' => "Order cannot be quoted while {$order->status}."]);
            }
            if ($order->issues === ['Delivery address required.']) {
                $order->issues = [];
            }
            if (($order->issues ?? []) !== []) {
                throw ValidationException::withMessages(['order' => 'Resolve catalog issues before quoting this order.']);
            }
            $order->forceFill([
                'shipping_address' => $data['shipping_address'],
                'shipping_amount' => $data['shipping_amount'],
                'total' => OrderMoney::decimal(OrderMoney::minor($order->subtotal, (in_array($order->currency, ['JPY']) ? 0 : (in_array($order->currency, ['KWD', 'BHD', 'OMR']) ? 3 : 2))) + OrderMoney::minor($data['shipping_amount'], (in_array($order->currency, ['JPY']) ? 0 : (in_array($order->currency, ['KWD', 'BHD', 'OMR']) ? 3 : 2))) - OrderMoney::minor($order->discount_amount, (in_array($order->currency, ['JPY']) ? 0 : (in_array($order->currency, ['KWD', 'BHD', 'OMR']) ? 3 : 2))), (in_array($order->currency, ['JPY']) ? 0 : (in_array($order->currency, ['KWD', 'BHD', 'OMR']) ? 3 : 2))),
                'shipping_quote_required' => false,
                'delivery_method' => $data['delivery_method'] ?? null,
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'duties_disclosure' => $data['duties_disclosure'] ?? 'Import duties and taxes, if any, are the buyer’s responsibility unless stated otherwise.',
                'payment_url' => $data['payment_url'] ?? null,
                'status' => ($order->source !== 'native_whatsapp' || $order->groups()->exists() || filled($data['payment_url'] ?? null)) ? 'awaiting_payment' : 'quoted',
            ])->save();
            app(UnifiedOrderService::class)->event($order, 'quoted', 'Shipping quote prepared');
            $this->audit->logCustom('commerce.order.quoted', ['order_id' => $order->id, 'number' => $order->number]);

            return $order->fresh('items');
        });
    }

    public function transition(Order $order, string $to, array $data = []): Order
    {
        return DB::transaction(function () use ($order, $to, $data): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $to) {
                return $locked->fresh('items');
            }
            $legacyDispatch = $locked->source === 'native_whatsapp' && ! $locked->groups()->exists() && $locked->status === 'processing' && $to === 'shipped';
            if (! $legacyDispatch && ! in_array($to, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Order cannot move from {$locked->status} to {$to}."]);
            }
            $unified = $locked->source !== 'native_whatsapp' || $locked->groups()->exists();
            if ($locked->status === 'draft' && $to !== 'cancelled') {
                app(OrderInventoryService::class)->reserve($locked->load('items'));
                $to = $locked->shipping_quote_required ? 'requested' : 'awaiting_payment';
                app(UnifiedOrderService::class)->event($locked, 'placed', 'Order placed');
            }
            if ($to === 'paid') {
                if ($locked->shipping_quote_required || $locked->total === null || ($locked->issues ?? []) !== []) {
                    throw ValidationException::withMessages(['payment' => 'Resolve order issues and prepare a total before confirming payment.']);
                }
                $locked->payment_state = 'paid';
                if ($unified) {
                    app(OrderInventoryService::class)->pay($locked->load('items'));
                } else {
                    $this->deductInventory($locked);
                }
                $locked->paid_at = now();
            }
            if ($to === 'cancelled' && $unified) {
                app(OrderInventoryService::class)->release($locked);
            }
            if ($to === 'cancelled' && ! $unified && $locked->inventory_adjusted_at && ! $locked->shipped_at) {
                $this->restoreInventory($locked);
            }
            if ($to === 'packed' && ! app(OrderPackingService::class)->complete($locked)) {
                throw ValidationException::withMessages(['packing' => 'Pack all ordered pieces before completing packing.']);
            }
            if ($to === 'shipped') {
                if ($unified && ($locked->payment_state !== 'paid' || ! app(OrderPackingService::class)->complete($locked))) {
                    throw ValidationException::withMessages(['shipping' => 'Confirm payment and pack every piece before shipping.']);
                }
                if ($unified && blank($data['tracking_number'] ?? null)) {
                    throw ValidationException::withMessages(['tracking_number' => 'A tracking number is required for dispatch.']);
                }
                $locked->tracking_number = $data['tracking_number'] ?? null;
                $locked->tracking_url = $data['tracking_url'] ?? null;
                $locked->shipped_at = now();
            }
            if ($to === 'completed') {
                $locked->delivered_at = now();
            }
            $locked->status = $to;
            $locked->save();
            if ($unified && $to === 'shipped') {
                $locked->shipment()->firstOrCreate([], ['carrier' => $data['carrier'] ?? 'Carrier', 'tracking_number' => $locked->tracking_number, 'tracking_url' => $locked->tracking_url, 'shipped_at' => $locked->shipped_at]);
            }
            if ($to === 'completed') {
                $locked->shipment()->update(['delivered_at' => $locked->delivered_at]);
            }
            app(UnifiedOrderService::class)->event($locked, $to, match ($to) {
                'paid' => 'Payment confirmed', 'processing' => 'Preparing your order', 'shipped' => 'Shipped / in transit', 'completed' => 'Delivered', 'cancelled' => 'Order cancelled', default => ucfirst(str_replace('_', ' ', $to))
            });
            $this->audit->logCustom('commerce.order.status_changed', ['order_id' => $locked->id, 'status' => $to]);

            if (! $unified && $to === 'shipped' && $locked->contact && $locked->contact->email) {
                Mail::to($locked->contact->email)
                    ->send(new OrderShippedMail($locked->fresh('items', 'contact')));
            }

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
