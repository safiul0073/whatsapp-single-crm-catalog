<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\Referral;
use App\Modules\Commerce\Storefront\Models\ReferralTransaction;
use App\Modules\Commerce\Storefront\Models\RefundRequest;
use App\Modules\Commerce\Storefront\Models\RefundRequestEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Refund requests in a referral store: the customer's referrer reviews first, the store owner
 * decides and is the only one who can complete the refund and move money.
 */
class RefundRequestService
{
    public const REFUNDABLE_PAYMENT_STATES = ['paid', 'partially_paid', 'partially_refunded'];

    /** Owner moves: the owner may also overrule a referrer who has not answered or who rejected. */
    public const ADMIN_TRANSITIONS = [
        'pending_referrer' => ['processing', 'admin_rejected'],
        'referrer_rejected' => ['processing', 'admin_rejected'],
        'awaiting_admin' => ['processing', 'admin_rejected'],
        'processing' => ['completed', 'admin_rejected'],
    ];

    public function __construct(
        private ReferralService $referrals,
        private RewardWalletService $wallet,
        private UnifiedOrderService $orders,
        private ReferralNotifier $notifier,
        private AuditLogService $audit,
    ) {}

    public function enabled(int $workspace): bool
    {
        $settings = $this->referrals->settings($workspace);

        return StoreFeatures::typeHas($workspace, 'referral') && $settings->enabled && $settings->refunds_enabled;
    }

    /**
     * What the customer actually paid for the order, in minor units.
     */
    public function paidMinor(Order $order): int
    {
        $precision = $order->moneyPrecision();
        $total = OrderMoney::minor((string) ($order->total ?? '0'), $precision);
        $recorded = OrderMoney::minor($order->paidAmount(), $precision);

        return $recorded > 0 ? min($recorded, $total) : $total;
    }

    /**
     * The amount of the order that can still be refunded, in minor units.
     */
    public function refundableMinor(Order $order): int
    {
        if (! in_array($order->payment_state, self::REFUNDABLE_PAYMENT_STATES, true) || $order->status === 'cancelled') {
            return 0;
        }

        return max(0, $this->paidMinor($order) - OrderMoney::minor((string) $order->refunded_amount, $order->moneyPrecision()));
    }

    /**
     * @param  array{amount: string, reason: string, details?: ?string}  $data
     */
    public function submit(Customer $customer, Order $order, array $data, string $submissionReference): RefundRequest
    {
        $workspace = $customer->workspace_id;
        abort_unless($this->enabled($workspace), 404);

        return DB::transaction(function () use ($customer, $order, $data, $submissionReference, $workspace): RefundRequest {
            $order = Order::query()->where('workspace_id', $workspace)->where('contact_id', $customer->contact_id)->lockForUpdate()->findOrFail($order->id);
            $existing = RefundRequest::query()->where('workspace_id', $workspace)->where('submission_reference', $submissionReference)->first();
            if ($existing) {
                abort_unless($existing->customer_id === $customer->id && $existing->order_id === $order->id, 409, 'This idempotency key has different contents.');

                return $existing;
            }
            abort_if(RefundRequest::query()->where('workspace_id', $workspace)->where('order_id', $order->id)->whereIn('status', RefundRequest::OPEN_STATUSES)->exists(), 422, 'This order already has a refund request in review.');
            $refundable = $this->refundableMinor($order);
            abort_if($refundable <= 0, 422, 'This order is not eligible for a refund.');
            $amount = OrderMoney::minor($data['amount'], $order->moneyPrecision());
            abort_if($amount <= 0 || $amount > $refundable, 422, 'The refund amount cannot be more than '.$order->currency.' '.OrderMoney::decimal($refundable, $order->moneyPrecision()).'.');

            $referrer = Referral::query()->where('workspace_id', $workspace)->where('referee_customer_id', $customer->id)->value('referrer_customer_id');
            do {
                $reference = 'RF-'.Str::upper(Str::random(8));
            } while (RefundRequest::query()->where('workspace_id', $workspace)->where('reference', $reference)->exists());
            $request = RefundRequest::query()->create([
                'workspace_id' => $workspace,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'referrer_customer_id' => $referrer,
                'reference' => $reference,
                'submission_reference' => $submissionReference,
                'amount_minor' => $amount,
                'currency' => $order->currency,
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'status' => $referrer ? 'pending_referrer' : 'awaiting_admin',
            ]);
            $this->record($request, 'customer', $customer->id, null, $request->status, $data['details'] ?? null);
            $this->orders->event($order, 'refund_requested_'.$request->id, 'Refund requested');
            $money = $order->currency.' '.OrderMoney::decimal($amount, $order->moneyPrecision());
            if ($referrer) {
                $this->notifier->customer($workspace, $referrer, 'Refund request to review', 'A customer you referred asked for a refund of '.$money.' on order '.$order->number.'.', ['type' => 'refund_review', 'reference' => $reference]);
            } else {
                $this->notifier->owner($workspace, 'Refund request '.$reference, $money.' · order '.$order->number, route('user.commerce.refunds.show', $request->id));
            }

            return $request;
        });
    }

    /**
     * The referrer's review. It only moves the request on; it never touches money.
     */
    public function referrerDecide(Customer $referrer, string $reference, string $decision, ?string $comment): RefundRequest
    {
        abort_unless($this->enabled($referrer->workspace_id), 404);

        return DB::transaction(function () use ($referrer, $reference, $decision, $comment): RefundRequest {
            $request = RefundRequest::query()->where('workspace_id', $referrer->workspace_id)->where('referrer_customer_id', $referrer->id)->where('reference', $reference)->lockForUpdate()->firstOrFail();
            if ($request->referrer_decision === $decision) {
                return $request;
            }
            abort_unless($request->status === 'pending_referrer', 422, 'This refund request has already been reviewed.');
            $to = $decision === 'approved' ? 'awaiting_admin' : 'referrer_rejected';
            $from = $request->status;
            $request->update(['status' => $to, 'referrer_decision' => $decision, 'referrer_comment' => $comment, 'referrer_decided_at' => now()]);
            $this->record($request, 'referrer', $referrer->id, $from, $to, $comment);
            $order = Order::query()->findOrFail($request->order_id);
            if ($decision === 'approved') {
                $this->orders->event($order, 'refund_referrer_approved_'.$request->id, 'Refund request sent to the store');
                $this->notifier->owner($request->workspace_id, 'Refund request '.$request->reference, 'Approved by the referrer · order '.$order->number, route('user.commerce.refunds.show', $request->id));
            } else {
                $this->orders->event($order, 'refund_referrer_rejected_'.$request->id, 'Refund request declined by your referrer'.($comment ? ': '.Str::limit($comment, 120) : ''));
            }

            return $request;
        });
    }

    /**
     * Owner decision. Completing a refund is the only step that moves money, and it runs once.
     *
     * @param  array{admin_note?: ?string, payout_method?: ?string, payout_channel?: ?string, payout_reference?: ?string}  $data
     */
    public function adminTransition(int $workspace, int $id, string $to, array $data, int $by): RefundRequest
    {
        return DB::transaction(function () use ($workspace, $id, $to, $data, $by): RefundRequest {
            $request = RefundRequest::query()->where('workspace_id', $workspace)->lockForUpdate()->findOrFail($id);
            if ($request->status === $to) {
                return $request;
            }
            abort_unless(in_array($to, self::ADMIN_TRANSITIONS[$request->status] ?? [], true), 422, __('This refund request can no longer be changed to :status.', ['status' => str_replace('_', ' ', $to)]));
            $from = $request->status;
            $order = Order::query()->where('workspace_id', $workspace)->lockForUpdate()->findOrFail($request->order_id);
            $request->fill(['status' => $to, 'admin_note' => $data['admin_note'] ?? $request->admin_note, 'decided_by' => $by]);
            if ($to === 'completed') {
                $this->complete($request, $order, $data, $by);
            }
            $request->save();
            $this->record($request, 'admin', $by, $from, $to, $data['admin_note'] ?? null);
            $this->audit->logCustom('commerce.refund.'.$to, ['refund_request_id' => $request->id, 'order_id' => $order->id, 'amount_minor' => $request->amount_minor]);
            $label = match ($to) {
                'processing' => 'Refund approved and being processed',
                'completed' => 'Refund completed',
                default => 'Refund request declined by the store',
            };
            $this->orders->event($order, 'refund_'.$to.'_'.$request->id, $label);
            $this->notifier->customer($workspace, $request->referrer_customer_id, 'Refund request '.$request->reference, $label.' · order '.$order->number, ['type' => 'refund_review', 'reference' => $request->reference]);

            return $request;
        });
    }

    /**
     * Move the money for a refund: order payment state, customer payout and referral commission.
     *
     * @param  array{payout_method?: ?string, payout_channel?: ?string, payout_reference?: ?string}  $data
     */
    private function complete(RefundRequest $request, Order $order, array $data, int $by): void
    {
        $precision = $order->moneyPrecision();
        $paid = $this->paidMinor($order);
        abort_if($request->amount_minor > $this->refundableMinor($order), 422, 'The order no longer has this much left to refund.');
        $refunded = OrderMoney::minor((string) $order->refunded_amount, $precision) + $request->amount_minor;
        $full = $refunded >= $paid;

        $method = $data['payout_method'] ?? 'manual';
        if ($method === 'wallet') {
            $this->wallet->post($request->workspace_id, $request->customer_id, $request->amount_minor, 'order_refund', 'refund:'.$request->id, $request, 'Refund for order '.$order->number, $by);
        }
        if ($full) {
            $this->wallet->refundOrderRedemption($request->workspace_id, $order);
        }
        [$reversed, $shortfall] = $this->reverseCommission($request, $order, $paid, $full);

        $order->update(['refunded_amount' => OrderMoney::decimal($refunded, $precision), 'refunded_at' => now(), 'payment_state' => $full ? 'refunded' : 'partially_refunded']);
        $request->fill([
            'payout_method' => $method,
            'payout_channel' => $method === 'manual' ? ($data['payout_channel'] ?? null) : null,
            'payout_reference' => $method === 'manual' ? ($data['payout_reference'] ?? null) : null,
            'commission_reversed_minor' => $reversed,
            'commission_shortfall_minor' => $shortfall,
            'completed_at' => now(),
        ]);
    }

    /**
     * Take back the referrer's commission in proportion to the refunded share of the order.
     * A reward that is still pending is reduced or cancelled; a credited one is debited from the
     * referrer's wallet as far as the balance allows, and the rest is reported as a shortfall.
     *
     * @return array{0: int, 1: int} reversed and shortfall, in minor units
     */
    private function reverseCommission(RefundRequest $request, Order $order, int $paid, bool $full): array
    {
        $reversed = 0;
        $shortfall = 0;
        $transactions = ReferralTransaction::query()->where('workspace_id', $request->workspace_id)->where('order_id', $order->id)->whereIn('status', ['pending', 'credited'])->with('referral')->lockForUpdate()->get();
        foreach ($transactions as $transaction) {
            $share = $paid > 0 ? min($transaction->reward_minor, intdiv($transaction->reward_minor * $request->amount_minor, $paid)) : 0;
            if ($transaction->status === 'pending') {
                $share = $full ? $transaction->reward_minor : $share;
                $transaction->update($full ? ['status' => 'cancelled'] : ['reward_minor' => $transaction->reward_minor - $share]);
                $reversed += $share;

                continue;
            }
            if ($share <= 0) {
                continue;
            }
            $referrer = $transaction->referral->referrer_customer_id;
            Customer::query()->where('workspace_id', $request->workspace_id)->lockForUpdate()->findOrFail($referrer);
            $debit = min($share, max(0, $this->wallet->balance($request->workspace_id, $referrer)));
            if ($debit > 0) {
                $this->wallet->post($request->workspace_id, $referrer, -$debit, 'referral_reversal', 'referral-reversal:'.$transaction->id.':refund:'.$request->id, $request, 'Commission reversed for refunded order '.$order->number);
            }
            $reversed += $debit;
            $shortfall += $share - $debit;
        }

        return [$reversed, $shortfall];
    }

    private function record(RefundRequest $request, string $actorType, ?int $actorId, ?string $from, string $to, ?string $comment): void
    {
        RefundRequestEvent::query()->create(['workspace_id' => $request->workspace_id, 'refund_request_id' => $request->id, 'actor_type' => $actorType, 'actor_id' => $actorId, 'from_status' => $from, 'to_status' => $to, 'comment' => $comment]);
    }
}
