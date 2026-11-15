<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Models\CashinRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\ReferralSetting;
use Illuminate\Support\Facades\DB;

/**
 * Wallet withdrawals: a request reserves the amount at once by debiting the wallet, a rejection
 * or cancellation credits it back, and a paid request keeps the debit as the final ledger entry.
 */
class WithdrawalService
{
    public const TRANSITIONS = [
        'pending' => ['approved', 'processing', 'rejected', 'paid'],
        'approved' => ['processing', 'paid', 'rejected'],
        'processing' => ['paid', 'rejected'],
    ];

    public function __construct(
        private ReferralService $referrals,
        private RewardWalletService $wallet,
        private WithdrawalScheduleService $schedule,
        private ReferralNotifier $notifier,
        private AuditLogService $audit,
    ) {}

    public function enabled(int $workspace): bool
    {
        $settings = $this->referrals->settings($workspace);

        return StoreFeatures::typeHas($workspace, 'referral') && $settings->enabled && $settings->cashin_enabled;
    }

    /**
     * Payout methods the owner accepts, keyed by method code.
     *
     * @return array<string, string>
     */
    public function methods(ReferralSetting $settings): array
    {
        $allowed = array_intersect_key(CashinRequest::METHODS, array_flip((array) $settings->cashin_methods));

        return $allowed ?: CashinRequest::METHODS;
    }

    /**
     * @param  array{amount: string, method: string, account_details: string}  $data
     */
    public function request(Customer $customer, array $data, string $submissionReference): CashinRequest
    {
        $workspace = $customer->workspace_id;
        abort_unless($this->enabled($workspace), 404);

        return DB::transaction(function () use ($customer, $data, $submissionReference, $workspace): CashinRequest {
            Customer::query()->where('workspace_id', $workspace)->lockForUpdate()->findOrFail($customer->id);
            $existing = CashinRequest::query()->where('workspace_id', $workspace)->where('submission_reference', $submissionReference)->first();
            if ($existing) {
                abort_unless($existing->customer_id === $customer->id, 409, 'This idempotency key has different contents.');

                return $existing;
            }
            $settings = $this->referrals->settings($workspace);
            $window = $this->schedule->currentWindow($settings);
            if (! $window) {
                $next = $this->schedule->nextWindow($settings);
                abort(422, $next ? 'Withdrawals are closed. The next window opens on '.$next['starts_at']->format('d M Y, h:i A').'.' : 'Withdrawals are closed.');
            }
            $store = StoreOrderSetting::forWorkspace($workspace);
            $precision = $store->precision();
            $amount = OrderMoney::minor($data['amount'], $precision);
            abort_if($amount <= 0, 422, 'Enter an amount to withdraw.');
            abort_if($amount < OrderMoney::minor($settings->min_cashin_balance, $precision), 422, 'The minimum withdrawal is '.$store->currency.' '.OrderMoney::decimal(OrderMoney::minor($settings->min_cashin_balance, $precision), $precision).'.');
            abort_if($settings->max_cashin_amount !== null && $amount > OrderMoney::minor($settings->max_cashin_amount, $precision), 422, 'The maximum withdrawal is '.$store->currency.' '.OrderMoney::decimal(OrderMoney::minor($settings->max_cashin_amount ?? 0, $precision), $precision).'.');
            abort_unless(array_key_exists($data['method'], $this->methods($settings)), 422, 'Choose an available payout method.');
            abort_if($amount > $this->wallet->balance($workspace, $customer->id), 422, 'Your available balance is lower than this amount.');

            $request = CashinRequest::query()->create([
                'workspace_id' => $workspace,
                'customer_id' => $customer->id,
                'amount_minor' => $amount,
                'currency' => $store->currency,
                'method' => $data['method'],
                'account_details' => $data['account_details'],
                'status' => 'pending',
                'submission_reference' => $submissionReference,
                'window_starts_at' => $window['starts_at']->utc(),
                'window_ends_at' => $window['ends_at']->utc(),
            ]);
            $this->wallet->post($workspace, $customer->id, -$amount, 'cashin_hold', 'cashin-hold:'.$request->id, $request, 'Withdrawal requested');
            $this->audit->logCustom('commerce.withdrawal.requested', ['cashin_request_id' => $request->id, 'customer_id' => $customer->id, 'amount_minor' => $amount]);
            $this->notifier->owner($workspace, 'New withdrawal request', $store->currency.' '.OrderMoney::decimal($amount, $precision).' via '.CashinRequest::METHODS[$data['method']], route('user.commerce.withdrawals.index'));

            return $request;
        });
    }

    public function cancel(Customer $customer, int $id): CashinRequest
    {
        return DB::transaction(function () use ($customer, $id): CashinRequest {
            $request = CashinRequest::query()->where('workspace_id', $customer->workspace_id)->where('customer_id', $customer->id)->lockForUpdate()->findOrFail($id);
            if ($request->status === 'cancelled') {
                return $request;
            }
            abort_unless($request->status === 'pending', 422, 'This withdrawal can no longer be cancelled.');
            $this->release($request, 'Withdrawal cancelled');
            $request->update(['status' => 'cancelled', 'processed_at' => now()]);
            $this->audit->logCustom('commerce.withdrawal.cancelled', ['cashin_request_id' => $request->id, 'customer_id' => $customer->id]);

            return $request;
        });
    }

    /**
     * Owner decision on a request. Repeating the status it already has changes nothing.
     */
    public function decide(int $workspace, int $id, string $status, ?string $note, ?string $payoutReference, int $by): CashinRequest
    {
        return DB::transaction(function () use ($workspace, $id, $status, $note, $payoutReference, $by): CashinRequest {
            $request = CashinRequest::query()->where('workspace_id', $workspace)->lockForUpdate()->findOrFail($id);
            if ($request->status === $status) {
                return $request;
            }
            abort_unless(in_array($status, self::TRANSITIONS[$request->status] ?? [], true), 422, __('This cash-in request can no longer be changed to :status.', ['status' => $status]));
            if ($status === 'rejected') {
                $this->release($request, 'Cash-in rejected');
            }
            $request->update([
                'status' => $status,
                'admin_note' => $note ?? $request->admin_note,
                'payout_reference' => $payoutReference ?? $request->payout_reference,
                'processed_by' => $by,
                'processed_at' => now(),
            ]);
            $this->audit->logCustom('commerce.withdrawal.'.$status, ['cashin_request_id' => $request->id, 'customer_id' => $request->customer_id, 'amount_minor' => $request->amount_minor]);
            $precision = StoreOrderSetting::forWorkspace($workspace)->precision();
            $this->notifier->customer($workspace, $request->customer_id, 'Withdrawal '.$status, 'Your withdrawal of '.$request->currency.' '.OrderMoney::decimal($request->amount_minor, $precision).' is now '.$status.'.'.($note ? ' '.$note : ''), ['type' => 'withdrawal', 'reference' => (string) $request->id]);

            return $request;
        });
    }

    /**
     * Return a reserved amount to the wallet. Written once per request.
     */
    private function release(CashinRequest $request, string $note): void
    {
        $this->wallet->post($request->workspace_id, $request->customer_id, $request->amount_minor, 'cashin_refund', 'cashin-refund:'.$request->id, $request, $note);
    }
}
