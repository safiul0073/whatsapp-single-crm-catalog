<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\OrderEditRequest;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * After payment proof, a customer asks the owner to change an order. An approval allows exactly one
 * edit; the edit goes through the same repricing and stock path as an owner edit, and the request keeps
 * the total before and after so the owner can collect or refund the difference outside the app.
 */
class OrderEditRequestService
{
    public const REQUESTABLE_PAYMENT_STATES = ['submitted', 'partially_paid', 'paid'];

    public function __construct(private UnifiedOrderService $orders) {}

    public function open(Order $order): ?OrderEditRequest
    {
        return OrderEditRequest::query()->where('order_id', $order->id)->whereIn('status', OrderEditRequest::OPEN_STATUSES)->latest('id')->first();
    }

    public function latest(Order $order): ?OrderEditRequest
    {
        return OrderEditRequest::query()->where('order_id', $order->id)->latest('id')->first();
    }

    public function orderAllowsEdits(Order $order): bool
    {
        return in_array($order->payment_state, self::REQUESTABLE_PAYMENT_STATES, true)
            && ! in_array($order->status, ['cancelled', 'completed'], true)
            && $order->isEditable();
    }

    /**
     * @return array{can_request_edit: bool, can_edit: bool}
     */
    public function eligibility(Order $order): array
    {
        $allowed = $this->orderAllowsEdits($order);
        $open = $this->open($order);

        return [
            'can_request_edit' => $allowed && ! $open,
            'can_edit' => $allowed && $open?->status === 'approved',
        ];
    }

    /**
     * Customer-facing fields added to the storefront order payload.
     *
     * @return array<string, mixed>
     */
    public function customerPayload(Order $order): array
    {
        $latest = $this->latest($order);
        $eligibility = $this->eligibility($order);

        return array_merge($eligibility, [
            'edit_request' => $latest ? $this->requestPayload($latest, $order) : null,
            'edit_lines' => $eligibility['can_edit'] ? $this->editLines($order) : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPayload(OrderEditRequest $request, Order $order): array
    {
        $precision = $order->moneyPrecision();
        $money = fn ($amount): ?string => $amount === null ? null : $this->signedDecimal($this->signedMinor((string) $amount, $precision), $precision);

        return [
            'reference' => $request->reference,
            'status' => $request->status,
            'customer_note' => $request->customer_note,
            'owner_note' => $request->owner_note,
            'created_at' => $request->created_at?->toIso8601String(),
            'decided_at' => $request->decided_at?->toIso8601String(),
            'used_at' => $request->used_at?->toIso8601String(),
            'currency' => $request->currency ?? $order->currency,
            'previous_total' => $money($request->previous_total),
            'new_total' => $money($request->new_total),
            'difference' => $money($request->difference),
        ];
    }

    /**
     * The order's current lines in the shape the edit endpoint accepts, with labels for display.
     * Owner-defined wholesale packs can only be kept or removed, so they carry keep_group_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function editLines(Order $order): array
    {
        $order->loadMissing(['groups', 'items']);
        $precision = $order->moneyPrecision();

        return $order->groups->map(function ($group) use ($order, $precision): array {
            $items = $order->items->where('group_id', $group->id);
            $line = match (true) {
                ! empty($group->pack_snapshot) => ['product_id' => $group->product_id, 'mode' => 'wholesale', 'color_id' => $group->color_id, 'box_count' => (int) $group->box_count, 'keep_group_id' => $group->id, 'locked' => true],
                $group->mode === 'retail' => ['product_id' => $group->product_id, 'mode' => 'retail', 'variant_id' => $items->first()?->variant_id, 'quantity' => (int) $group->quantity],
                default => ['product_id' => $group->product_id, 'mode' => 'wholesale', 'color_id' => $group->color_id, 'box_count' => (int) $group->box_count],
            };
            $first = $items->first();
            $lineTotal = $items->sum(fn ($item): int => OrderMoney::minor((string) $item->line_total, $precision));

            return array_merge(['locked' => false], $line, [
                'product_name' => $group->product_name ?? $first?->product_name,
                'color_name' => $group->color_name ?? ($first?->attributes['color'] ?? null),
                'size' => $group->mode === 'retail' ? ($first?->attributes['size'] ?? null) : null,
                'pack_name' => $group->pack_snapshot['name'] ?? null,
                'unit_price' => $first ? OrderMoney::decimal(OrderMoney::minor((string) $first->unit_price, $precision), $precision) : null,
                'line_total' => OrderMoney::decimal($lineTotal, $precision),
            ]);
        })->values()->all();
    }

    public function submit(Customer $customer, Order $order, ?string $note, string $submissionReference): OrderEditRequest
    {
        return DB::transaction(function () use ($customer, $order, $note, $submissionReference): OrderEditRequest {
            $order = Order::query()->where('workspace_id', $customer->workspace_id)->where('contact_id', $customer->contact_id)->lockForUpdate()->findOrFail($order->id);
            $existing = OrderEditRequest::query()->where('workspace_id', $order->workspace_id)->where('submission_reference', $submissionReference)->first();
            if ($existing) {
                abort_unless($existing->order_id === $order->id && $existing->customer_id === $customer->id, 409, 'This idempotency key has different contents.');

                return $existing;
            }
            abort_unless($this->orderAllowsEdits($order), 422, 'This order can no longer be changed. Please contact the store.');
            abort_if($this->open($order) !== null, 422, 'This order already has an edit request.');

            do {
                $reference = 'OE-'.Str::upper(Str::random(8));
            } while (OrderEditRequest::query()->where('workspace_id', $order->workspace_id)->where('reference', $reference)->exists());

            $request = OrderEditRequest::query()->create([
                'workspace_id' => $order->workspace_id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'reference' => $reference,
                'submission_reference' => $submissionReference,
                'status' => 'pending',
                'customer_note' => $note,
                'currency' => $order->currency,
            ]);
            $this->orders->event($order, 'edit_requested_'.$request->id, 'Customer requested an order edit');

            return $request;
        });
    }

    /**
     * The owner's answer: approve or reject a pending request, or revoke an approval that was not used.
     */
    public function decide(Order $order, OrderEditRequest $request, string $decision, ?string $note, User $staff): OrderEditRequest
    {
        return DB::transaction(function () use ($order, $request, $decision, $note, $staff): OrderEditRequest {
            $request = OrderEditRequest::query()->where('order_id', $order->id)->lockForUpdate()->findOrFail($request->id);
            [$from, $to, $label] = match ($decision) {
                'approve' => ['pending', 'approved', 'Edit request approved · you can change your order once'],
                'reject' => ['pending', 'rejected', 'Edit request declined'],
                'revoke' => ['approved', 'revoked', 'Edit permission withdrawn'],
            };
            abort_unless($request->status === $from, 422, 'This edit request has already been answered.');
            abort_if($decision === 'approve' && ! $this->orderAllowsEdits($order), 422, 'This order can no longer be edited.');

            $request->update(['status' => $to, 'owner_note' => $note, 'decided_by' => $staff->id, 'decided_at' => now()]);
            $this->orders->event($order, 'edit_'.$to.'_'.$request->id, $label);

            return $request;
        });
    }

    /**
     * Totals the customer's new lines would have, without saving anything.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preview(Order $order, Workspace $workspace, array $data): array
    {
        abort_unless($this->eligibility($order)['can_edit'], 422, 'Ask the store to approve an edit first.');
        $order->loadMissing('groups');
        $preview = $this->orders->preview($workspace, [
            'source' => $order->source,
            'adjustments' => $order->adjustments ?? [],
            'shipping_address' => array_replace(['country' => ''], $order->shipping_address ?? []),
            'shipping_method_id' => $order->shipping_method_id,
            'groups' => $this->expandKeptGroups($order, $data['groups']),
        ]);
        $precision = $order->moneyPrecision();
        $previous = OrderMoney::minor((string) ($order->total ?? '0'), $precision);
        $next = $preview['total'] === null ? null : OrderMoney::minor((string) $preview['total'], $precision);

        return [
            'currency' => $order->currency,
            'subtotal' => OrderMoney::decimal(OrderMoney::minor((string) $preview['subtotal'], $precision), $precision),
            'discount_amount' => OrderMoney::decimal(OrderMoney::minor((string) $preview['discount_amount'], $precision), $precision),
            'shipping_amount' => $preview['shipping_amount'] === null ? null : OrderMoney::decimal(OrderMoney::minor((string) $preview['shipping_amount'], $precision), $precision),
            'previous_total' => OrderMoney::decimal($previous, $precision),
            'total' => $next === null ? null : OrderMoney::decimal($next, $precision),
            'difference' => $next === null ? null : $this->signedDecimal($next - $previous, $precision),
        ];
    }

    /**
     * Uses the approval: saves the customer's new lines once and records the change in total.
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(Customer $customer, Order $order, Workspace $workspace, array $data): Order
    {
        return DB::transaction(function () use ($customer, $order, $workspace, $data): Order {
            $order = Order::query()->where('workspace_id', $customer->workspace_id)->where('contact_id', $customer->contact_id)->lockForUpdate()->findOrFail($order->id);
            $request = OrderEditRequest::query()->where('order_id', $order->id)->where('status', 'approved')->lockForUpdate()->latest('id')->first();
            abort_unless($request && $this->orderAllowsEdits($order), 422, 'Ask the store to approve an edit first.');

            $precision = $order->moneyPrecision();
            $previous = OrderMoney::minor((string) ($order->total ?? '0'), $precision);
            $updated = $this->orders->updateOrder($order, $workspace, ['submission_reference' => $order->submission_reference, 'groups' => $data['groups']]);
            $next = OrderMoney::minor((string) ($updated->total ?? '0'), $precision);
            $difference = $next - $previous;

            $request->update([
                'status' => 'used',
                'used_at' => now(),
                'currency' => $updated->currency,
                'previous_total' => OrderMoney::decimal($previous, $precision),
                'new_total' => OrderMoney::decimal($next, $precision),
                'difference' => $this->signedDecimal($difference, $precision),
            ]);
            $money = fn (int $minor): string => $updated->currency.' '.OrderMoney::decimal(abs($minor), $precision);
            $label = 'Customer edited the order · total '.$money($previous).' → '.$money($next).match (true) {
                $difference > 0 => ' · collect '.$money($difference),
                $difference < 0 => ' · refund '.$money($difference),
                default => '',
            };
            $this->orders->event($updated, 'edit_used_'.$request->id, $label);

            return $updated;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function expandKeptGroups(Order $order, array $groups): array
    {
        return array_map(function (array $selection) use ($order): array {
            if (empty($selection['keep_group_id'])) {
                unset($selection['keep_group_id']);

                return $selection;
            }
            $kept = $order->groups->firstWhere('id', (int) $selection['keep_group_id']);
            abort_unless($kept && ! empty($kept->pack_snapshot), 422, 'A kept pack does not belong to this order.');

            return ['product_id' => $kept->product_id, 'mode' => 'wholesale', 'color_id' => $kept->color_id, 'box_count' => $kept->box_count, 'size_quantities' => $kept->ratio, 'pack_snapshot' => $kept->pack_snapshot];
        }, $groups);
    }

    /**
     * The total difference can be negative (money owed back to the customer); OrderMoney only handles amounts.
     */
    private function signedDecimal(int $minor, int $precision): string
    {
        return ($minor < 0 ? '-' : '').OrderMoney::decimal(abs($minor), $precision);
    }

    private function signedMinor(string $amount, int $precision): int
    {
        return str_starts_with($amount, '-') ? -OrderMoney::minor(substr($amount, 1), $precision) : OrderMoney::minor($amount, $precision);
    }
}
