<?php

namespace App\Modules\Shipping\Services;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderGroup;
use App\Modules\Commerce\Models\OrderShipment;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function __construct(
        private ShippingAuditService $audit,
        private SystemNotificationService $notifications,
        private ShippingAlertService $alerts,
    ) {}

    /**
     * Order of normal progress; a tracking update never moves a shipment backwards.
     */
    private const PROGRESS = ['booked' => 1, 'in_transit' => 2, 'out_for_delivery' => 3, 'delivered' => 4];

    /**
     * Opens the first shipping record for an order. Safe to call more than once.
     */
    public function createForOrder(Order $order): ?Shipment
    {
        if ($order->fulfillment_type === 'pickup' || Shipment::query()->where('order_id', $order->id)->exists()) {
            return null;
        }

        return $this->create($order);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Order $order, array $data = [], ?User $user = null): Shipment
    {
        return DB::transaction(function () use ($order, $data, $user): Shipment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $sequence = Shipment::query()->where('order_id', $order->id)->count() + 1;
            $address = (array) $order->shipping_address;
            $snapshot = (array) $order->customer_snapshot;

            $shipment = Shipment::query()->create(array_merge([
                'workspace_id' => $order->workspace_id,
                'order_id' => $order->id,
                'reference' => $order->number.'-S'.$sequence,
                'status' => 'pending',
                'recipient_name' => $address['name'] ?? $snapshot['name'] ?? null,
                'phone' => $address['phone'] ?? $snapshot['phone'] ?? null,
                'country_code' => isset($address['country']) ? strtoupper((string) $address['country']) : null,
                'state' => $address['state'] ?? null,
                'city' => $address['city'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'address_line1' => $address['line1'] ?? null,
                'address_line2' => $address['line2'] ?? null,
                'currency' => $order->currency ?: 'USD',
                'total_pieces' => (int) $order->items()->sum('quantity'),
                'total_sets' => (int) OrderGroup::query()->where('order_id', $order->id)->sum('box_count'),
            ], $data));

            $this->audit->record($order->workspace_id, 'shipment.created', 'Shipping record opened for order '.$order->number, $shipment, null, ['reference' => $shipment->reference], $user?->id);

            return $shipment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Shipment $shipment, array $data, ?User $user = null): Shipment
    {
        $addressFields = ['recipient_name', 'phone', 'country_code', 'state', 'city', 'postal_code', 'address_line1', 'address_line2', 'address_type'];
        $old = $shipment->only(array_keys($data));
        $shipment->fill($data);
        $addressChanged = $shipment->isDirty($addressFields);
        if ($addressChanged) {
            $shipment->address_validated_at = $shipment->hasCompleteAddress() ? now() : null;
        }
        $changed = array_keys($shipment->getDirty());
        $shipment->save();

        if ($changed !== []) {
            $this->audit->record($shipment->workspace_id, $addressChanged ? 'shipment.address_updated' : 'shipment.updated', 'Shipment '.$shipment->reference.' updated', $shipment, array_intersect_key($old, array_flip($changed)), $shipment->only($changed), $user?->id);
        }

        return $shipment;
    }

    /**
     * Replaces the carton list of a shipment.
     *
     * @param  list<array<string, mixed>>  $cartons
     */
    public function syncCartons(Shipment $shipment, array $cartons, ?User $user = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $cartons, $user): Shipment {
            $old = $shipment->cartons()->get(['quantity', 'actual_weight_kg', 'length_cm', 'width_cm', 'height_cm', 'packaging_type'])->toArray();
            $shipment->cartons()->delete();
            foreach ($cartons as $carton) {
                $shipment->cartons()->create([
                    'workspace_id' => $shipment->workspace_id,
                    'quantity' => (int) ($carton['quantity'] ?? 1),
                    'actual_weight_kg' => $carton['actual_weight_kg'],
                    'length_cm' => $carton['length_cm'],
                    'width_cm' => $carton['width_cm'],
                    'height_cm' => $carton['height_cm'],
                    'packaging_type' => $carton['packaging_type'] ?? 'carton',
                ]);
            }
            $this->audit->record($shipment->workspace_id, 'shipment.cartons_updated', 'Cartons updated for '.$shipment->reference, $shipment, ['cartons' => $old], ['cartons' => $cartons], $user?->id);

            return $shipment->load('cartons');
        });
    }

    /**
     * Saves booking details entered by staff. Nothing is sent to a courier from here.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordBooking(Shipment $shipment, array $data, ?User $user = null): Shipment
    {
        if ($shipment->approved_quote_id === null) {
            throw ValidationException::withMessages(['tracking_number' => __('Approve a carrier or agent before saving tracking details.')]);
        }
        if ($shipment->wasBookedThroughApi() && filled($shipment->tracking_number) && $data['tracking_number'] !== $shipment->tracking_number) {
            throw ValidationException::withMessages(['tracking_number' => __('This shipment was booked through the courier API. Cancel that booking instead of changing its tracking number.')]);
        }

        $old = $shipment->only(['tracking_number', 'tracking_url', 'status']);
        $status = $data['status'] ?? (in_array($shipment->status, ['approved', 'quoted', 'pending'], true) ? 'booked' : $shipment->status);

        $shipment->update([
            'tracking_number' => $data['tracking_number'],
            'tracking_url' => ($data['tracking_url'] ?? null) ?: Shipment::publicTrackingUrl($shipment->carrier, $data['tracking_number']),
            'status' => $status,
            'shipped_at' => in_array($status, ['in_transit', 'delivered'], true) ? ($shipment->shipped_at ?? now()) : $shipment->shipped_at,
            'delivered_at' => $status === 'delivered' ? ($shipment->delivered_at ?? now()) : $shipment->delivered_at,
        ]);

        $this->mirrorToOrder($shipment, $old['tracking_number']);
        $this->audit->record($shipment->workspace_id, 'shipment.booked', 'Tracking saved for '.$shipment->reference.': '.$shipment->tracking_number, $shipment, $old, $shipment->only(['tracking_number', 'tracking_url', 'status']), $user?->id);

        return $shipment;
    }

    /**
     * Applies the latest courier status from a tracking update. Closed, cancelled and
     * delivered shipments are left alone, and normal progress never goes backwards.
     */
    public function applyTrackingStatus(Shipment $shipment, ?string $status, ?string $description = null, ?string $deliveredAt = null): bool
    {
        $current = $shipment->status;
        if ($status === null || $status === $current || in_array($current, ['delivered', 'closed', 'cancelled'], true)) {
            return false;
        }
        if ($status !== 'exception' && $current !== 'exception' && (self::PROGRESS[$status] ?? 0) < (self::PROGRESS[$current] ?? 0)) {
            return false;
        }
        if ($status === 'booked' && $current === 'exception') {
            return false;
        }

        $shipment->update([
            'status' => $status,
            'shipped_at' => in_array($status, ['in_transit', 'out_for_delivery', 'exception', 'delivered'], true) ? ($shipment->shipped_at ?? now()) : $shipment->shipped_at,
            'delivered_at' => $status === 'delivered' ? ($deliveredAt ? Carbon::parse($deliveredAt) : now()) : $shipment->delivered_at,
            'exception_reason' => $status === 'exception' ? mb_substr((string) ($description ?: 'Courier reported a delivery problem'), 0, 255) : null,
            'exception_at' => $status === 'exception' ? now() : null,
        ]);

        $this->mirrorToOrder($shipment);
        $this->audit->record($shipment->workspace_id, 'tracking.status_changed', $shipment->reference.' moved from '.$current.' to '.$status.' by courier tracking'.($description ? ': '.$description : ''), $shipment, ['status' => $current], ['status' => $status], null);

        if ($status === 'exception') {
            $this->alerts->notify(
                $shipment,
                __('Delivery exception — :reference', ['reference' => $shipment->reference]),
                (string) $shipment->exception_reason,
                'shipping.shipments.manage',
            );
        }

        return true;
    }

    public function setStatus(Shipment $shipment, string $status, ?User $user = null): Shipment
    {
        $old = $shipment->status;
        if ($status === 'closed' && $shipment->needs_review) {
            throw ValidationException::withMessages(['status' => __('Review the unexpected charges before closing this shipment.')]);
        }

        $shipment->update([
            'status' => $status,
            'shipped_at' => in_array($status, ['in_transit', 'out_for_delivery', 'delivered'], true) ? ($shipment->shipped_at ?? now()) : $shipment->shipped_at,
            'delivered_at' => in_array($status, ['delivered', 'closed'], true) ? ($shipment->delivered_at ?? now()) : $shipment->delivered_at,
            'closed_at' => $status === 'closed' ? now() : null,
            'exception_reason' => $status === 'exception' ? $shipment->exception_reason : null,
            'exception_at' => $status === 'exception' ? ($shipment->exception_at ?? now()) : null,
        ]);

        $this->mirrorToOrder($shipment);
        $this->audit->record($shipment->workspace_id, 'shipment.status_changed', $shipment->reference.' moved from '.$old.' to '.$status, $shipment, ['status' => $old], ['status' => $status], $user?->id);

        return $shipment;
    }

    /**
     * Records what the courier or agent actually billed and reconciles it against the approved cost.
     *
     * @param  array{billed_amount: string|int|float, billed_currency: string, billed_exchange_rate?: string|int|float|null, payment_status?: string, charges?: list<array<string, mixed>>}  $data
     */
    public function recordBilling(Shipment $shipment, array $data, ?User $user = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $user): Shipment {
            $currency = strtoupper($data['billed_currency']);
            $rate = $currency === $shipment->currency ? '1' : (string) ($data['billed_exchange_rate'] ?? '1');
            $precision = ShippingMoney::precision($shipment->currency);
            $final = ShippingMoney::convert($data['billed_amount'], $rate, $precision);
            $old = $shipment->only(['final_cost', 'billed_amount', 'billed_currency', 'payment_status', 'needs_review']);

            $overApproved = $shipment->approved_cost !== null && ShippingMoney::isGreater($final, $shipment->approved_cost, $precision);
            $unapproved = $shipment->approved_cost === null;
            $paymentStatus = $data['payment_status'] ?? $shipment->payment_status;

            $shipment->update([
                'billed_amount' => ShippingMoney::round($data['billed_amount'], ShippingMoney::precision($currency)),
                'billed_currency' => $currency,
                'billed_exchange_rate' => $rate,
                'final_cost' => $final,
                'payment_status' => $paymentStatus,
                'paid_at' => $paymentStatus === 'paid' ? ($shipment->paid_at ?? now()) : null,
                'needs_review' => $overApproved || $unapproved,
                'review_reason' => $overApproved
                    ? 'Billed '.$final.' '.$shipment->currency.' exceeds the approved '.ShippingMoney::round($shipment->approved_cost, $precision).' '.$shipment->currency
                    : ($unapproved ? 'Billed without an approved shipping cost' : null),
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $shipment->charges()->delete();
            foreach ($data['charges'] ?? [] as $charge) {
                if (blank($charge['reason'] ?? null) || (float) ($charge['amount'] ?? 0) <= 0) {
                    continue;
                }
                $shipment->charges()->create([
                    'workspace_id' => $shipment->workspace_id,
                    'reason' => $charge['reason'],
                    'label' => $charge['label'] ?? null,
                    'amount' => ShippingMoney::round($charge['amount'], ShippingMoney::precision($currency)),
                ]);
            }

            $this->audit->record($shipment->workspace_id, 'billing.recorded', 'Billed amount recorded for '.$shipment->reference.': '.$final.' '.$shipment->currency, $shipment, $old, $shipment->only(['final_cost', 'billed_amount', 'billed_currency', 'payment_status', 'needs_review']), $user?->id);

            if ($shipment->needs_review) {
                $this->notifyOwner($shipment);
            }

            return $shipment;
        });
    }

    /**
     * The owner accepts an unexpected charge, clearing the review flag.
     */
    public function resolveReview(Shipment $shipment, User $user, ?string $note = null): Shipment
    {
        $shipment->update(['needs_review' => false, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        $this->audit->record($shipment->workspace_id, 'billing.review_approved', 'Unexpected charge approved for '.$shipment->reference.($note ? ': '.$note : ''), $shipment, ['review_reason' => $shipment->review_reason], ['final_cost' => $shipment->final_cost], $user->id);

        return $shipment;
    }

    public function variance(Shipment $shipment): ?string
    {
        if ($shipment->final_cost === null || $shipment->approved_cost === null) {
            return null;
        }

        return ShippingMoney::difference($shipment->final_cost, $shipment->approved_cost, ShippingMoney::precision($shipment->currency));
    }

    /**
     * Keeps the single customer-facing shipment record of the order pointing at the
     * first shipment that has tracking, so existing order screens keep working.
     * Only the courier name and tracking details are copied — never costs or agents.
     * A tracking number staff typed on the order by hand is left alone.
     */
    private function mirrorToOrder(Shipment $shipment, ?string $previousTracking = null): void
    {
        if (blank($shipment->tracking_number)) {
            return;
        }

        $first = Shipment::query()->where('order_id', $shipment->order_id)->whereNotNull('tracking_number')->where('status', '!=', 'cancelled')->orderBy('id')->first();
        if (! $first || $first->id !== $shipment->id) {
            return;
        }

        $ownTracking = Shipment::query()->where('order_id', $shipment->order_id)->whereNotNull('tracking_number')->pluck('tracking_number')->all();
        $existing = OrderShipment::query()->where('order_id', $shipment->order_id)->first();
        if ($existing && filled($existing->tracking_number) && ! in_array($existing->tracking_number, array_merge($ownTracking, [$previousTracking]), true)) {
            return;
        }

        OrderShipment::query()->updateOrCreate(['order_id' => $shipment->order_id], array_filter([
            'carrier' => $shipment->carrierLabel() ?? $existing?->carrier ?? 'Carrier',
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            'shipped_at' => $shipment->shipped_at ?? $existing?->shipped_at ?? now(),
            'delivered_at' => $shipment->delivered_at,
        ], fn ($value): bool => $value !== null));

        Order::query()->whereKey($shipment->order_id)->update(['tracking_number' => $shipment->tracking_number, 'tracking_url' => $shipment->tracking_url]);
    }

    private function notifyOwner(Shipment $shipment): void
    {
        $owner = Workspace::query()->find($shipment->workspace_id)?->owner;
        if (! $owner) {
            return;
        }

        rescue(fn () => $this->notifications->send($owner, [
            'title' => __('Shipping charge needs review — :reference', ['reference' => $shipment->reference]),
            'body' => (string) $shipment->review_reason,
            'url' => route('user.shipping.shipments.show', $shipment),
        ], 'shipping'), report: true);
    }
}
