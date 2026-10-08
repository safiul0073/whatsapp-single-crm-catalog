<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(protected UnifiedOrderService $orders, protected OrderInventoryService $inventory, protected AuditLogService $audit) {}

    public function checkout(Workspace $workspace, array $data, User $staff): Order
    {
        return DB::transaction(function () use ($workspace, $data, $staff): Order {
            Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $data['source'] = 'pos';
            $order = $this->orders->create($workspace, $data);
            if (! $order->wasRecentlyCreated) {
                return $order->load('payments');
            }
            if ($order->total === null && (isset($data['payment']) || ! empty($data['handover']))) {
                $this->invalid('payment', 'Prepare a shipping quote before accepting payment or handing over goods.');
            }
            if ($order->contact_id && (blank($order->contact?->name) || blank($order->contact?->phone))) {
                $this->invalid('customer', 'An identified customer must have a name and phone number.');
            }
            if (! $order->shipping_quote_required) {
                $order->update(['status' => 'processing']);
            }
            if (isset($data['payment'])) {
                $this->recordPayment($order, $data['payment'], $staff);
            }
            $order = $order->fresh('payments');
            if (! $order->contact_id && ($order->fulfillment_type !== 'pickup' || $order->payment_state !== 'paid' || empty($data['handover']))) {
                $this->invalid('customer', 'Walk-in sales must be fully paid and handed over for pickup. Identify the customer for other sales.');
            }
            if (! empty($data['handover'])) {
                $order = $this->pickup($order);
            }

            $this->audit->logCustom('commerce.pos.checkout', ['order_id' => $order->id, 'staff_id' => $staff->id]);

            return $order->fresh(['items', 'groups', 'payments']);
        }, 3);
    }

    public function recordPayment(Order $order, array $data, User $staff): OrderPayment
    {
        return DB::transaction(function () use ($order, $data, $staff): OrderPayment {
            Workspace::query()->lockForUpdate()->findOrFail($order->workspace_id);
            $locked = Order::query()->where('workspace_id', $order->workspace_id)->lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->source === 'pos', 422, 'Use the existing payment workflow for this order.');
            $precision = $locked->moneyPrecision();
            foreach (['amount', 'tendered_amount'] as $field) {
                if (isset($data[$field]) && ! preg_match('/^\d{1,10}(?:\.\d{0,'.$precision.'}0*)?$/', (string) $data[$field])) {
                    $this->invalid($field, 'Use the order currency’s supported decimal precision.');
                }
            }
            $amount = OrderMoney::minor($data['amount'], $precision);
            $tendered = isset($data['tendered_amount']) ? OrderMoney::minor($data['tendered_amount'], $precision) : null;
            $payload = ['order_id' => $locked->id, 'amount' => $amount, 'currency' => $data['currency'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'tendered_amount' => $tendered];
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $existing = OrderPayment::query()->where('workspace_id', $locked->workspace_id)->where('submission_reference', $data['submission_reference'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'This payment reference has different contents.');

                return $existing;
            }
            if ($locked->status === 'cancelled' || $locked->total === null || $locked->shipping_quote_required) {
                $this->invalid('payment', 'This order cannot accept payment until its total is confirmed.');
            }
            if ($data['currency'] !== $locked->currency) {
                $this->invalid('currency', 'Payment currency must match the order.');
            }
            $locked->setRelation('payments', $locked->payments()->lockForUpdate()->get());
            if ($amount < 1 || $amount > OrderMoney::minor($locked->balanceDue(), $precision)) {
                $this->invalid('amount', 'Payment must be greater than zero and cannot exceed the remaining balance.');
            }
            $methods = $this->paymentMethods($locked->workspace_id);
            if (! in_array($data['method'], array_column($methods, 'id'), true)) {
                $this->invalid('method', 'Choose cash or an active manual payment method.');
            }
            if ($tendered !== null && ($data['method'] !== 'cash' || $tendered < $amount)) {
                $this->invalid('tendered_amount', 'Cash tendered must cover the recorded payment and is only available for cash.');
            }
            $payment = $locked->payments()->create([
                'workspace_id' => $locked->workspace_id, 'amount' => OrderMoney::decimal($amount, $precision), 'currency' => $locked->currency,
                'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'staff_id' => $staff->id, 'recorded_at' => now(),
                'submission_reference' => $data['submission_reference'], 'payload_hash' => $hash,
                'tendered_amount' => $tendered === null ? null : OrderMoney::decimal($tendered, $precision),
                'change_amount' => OrderMoney::decimal($tendered === null ? 0 : $tendered - $amount, $precision),
            ]);
            $locked->setRelation('payments', $locked->payments()->lockForUpdate()->get());
            $paid = OrderMoney::minor($locked->balanceDue(), $precision) === 0;
            $locked->update(['payment_state' => $paid ? 'paid' : 'partially_paid', 'paid_at' => $paid ? now() : null]);
            $this->orders->event($locked, 'payment_'.$payment->id, 'Payment recorded: '.$locked->currency.' '.OrderMoney::decimal($amount, $precision));

            $this->audit->logCustom('commerce.pos.payment_recorded', ['order_id' => $locked->id, 'payment_id' => $payment->id, 'staff_id' => $staff->id]);

            return $payment;
        }, 3);
    }

    public function pickup(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            Workspace::query()->lockForUpdate()->findOrFail($order->workspace_id);
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->source === 'pos' && $locked->fulfillment_type === 'pickup', 422, 'This is not a POS pickup order.');
            if ($locked->status === 'completed') {
                return $locked;
            }
            $this->assertCanFulfill($locked);
            $this->inventory->pay($locked->load('items'));
            $locked->update(['status' => 'completed', 'delivered_at' => now()]);
            $this->orders->event($locked, 'completed', 'Collected at counter');
            $this->audit->logCustom('commerce.pos.pickup_completed', ['order_id' => $locked->id]);

            return $locked;
        }, 3);
    }

    public function transition(Order $order, string $to, array $data = []): Order
    {
        return DB::transaction(function () use ($order, $to, $data): Order {
            Workspace::query()->lockForUpdate()->findOrFail($order->workspace_id);
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === $to) {
                return $locked;
            }
            if ($to === 'cancelled') {
                if ($locked->inventory_adjusted_at || $locked->payments()->lockForUpdate()->get()->isNotEmpty() || $locked->status === 'completed') {
                    $this->invalid('status', 'Sales with confirmed payments or handed-over goods cannot be cancelled.');
                }
                $this->inventory->release($locked);
            } else {
                $allowed = ['processing' => ['packed'], 'packed' => ['shipped'], 'shipped' => ['completed']];
                if (! in_array($to, $allowed[$locked->status] ?? [], true) || $locked->fulfillment_type !== 'delivery') {
                    $this->invalid('status', 'Use payment collection and pickup completion for POS sales, or follow the delivery packing workflow.');
                }
                $this->assertCanFulfill($locked);
                if (in_array($to, ['packed', 'shipped'], true) && ! app(OrderPackingService::class)->complete($locked->load('items'))) {
                    $this->invalid('packing', 'Pack every ordered piece before dispatch.');
                }
                if ($to === 'packed') {
                    $locked->packed_at = now();
                }
                if ($to === 'shipped') {
                    if (blank($data['tracking_number'] ?? null)) {
                        $this->invalid('tracking_number', 'A tracking number is required for dispatch.');
                    }
                    $this->inventory->pay($locked->load('items'));
                    $locked->tracking_number = $data['tracking_number'];
                    $locked->tracking_url = $data['tracking_url'] ?? null;
                    $locked->shipped_at = now();
                    $locked->shipment()->firstOrCreate([], ['carrier' => $data['carrier'] ?? 'Carrier', 'tracking_number' => $locked->tracking_number, 'tracking_url' => $locked->tracking_url, 'shipped_at' => $locked->shipped_at]);
                }
                if ($to === 'completed') {
                    $locked->delivered_at = now();
                    $locked->shipment()->update(['delivered_at' => $locked->delivered_at]);
                }
            }
            $this->audit->logCustom('commerce.order.status_changed', ['order_id' => $locked->id, 'status' => $to]);
            $locked->status = $to;
            $locked->save();
            $this->orders->event($locked, $to, match ($to) {
                'shipped' => 'Shipped / in transit', 'completed' => 'Delivered', 'cancelled' => 'Order cancelled', default => 'All boxes packed',
            });

            return $locked;
        }, 3);
    }

    public function assertCanFulfill(Order $order): void
    {
        if ($order->status === 'cancelled' || $order->total === null || $order->shipping_quote_required || ($order->issues ?? []) !== []) {
            $this->invalid('fulfillment', 'Confirm the total and resolve order issues before handing over goods.');
        }
        if ($order->payment_state !== 'paid' && ! $order->contact_id) {
            $this->invalid('customer', 'An identified customer is required for a credit sale.');
        }
    }

    public function paymentMethods(int $workspaceId): array
    {
        return array_merge([['id' => 'cash', 'name' => 'Cash']], collect(StoreOrderSetting::paymentMethods($workspaceId, true))->reject(fn (array $method): bool => $method['id'] === 'cash')->map(fn (array $method): array => ['id' => $method['id'], 'name' => $method['name']])->values()->all());
    }

    public function customerBalances(Contact $contact): array
    {
        return Order::query()->where('workspace_id', $contact->workspace_id)->where('contact_id', $contact->id)->where('source', 'pos')->where('status', '!=', 'cancelled')->whereNotNull('total')->with('payments')->get()
            ->groupBy('currency')->sortKeys()->map(function ($orders, string $currency): array {
                $precision = $orders->first()->moneyPrecision();

                return ['currency' => $currency, 'amount' => OrderMoney::decimal($orders->sum(fn (Order $order): int => OrderMoney::minor($order->balanceDue(), $precision)), $precision)];
            })->values()->all();
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
