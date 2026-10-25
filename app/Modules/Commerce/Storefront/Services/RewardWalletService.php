<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\ReferralSetting;
use App\Modules\Commerce\Storefront\Models\RewardLedgerEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RewardWalletService
{
    public function balance(int $workspace, int $customer): int
    {
        return (int) RewardLedgerEntry::query()->where('workspace_id', $workspace)->where('customer_id', $customer)->sum('amount_minor');
    }

    /**
     * Append a signed entry to the customer's wallet. Entries with an idempotency key are written once.
     */
    public function post(int $workspace, int $customer, int $amount, string $type, ?string $key = null, ?Model $source = null, ?string $note = null, ?int $by = null): RewardLedgerEntry
    {
        return DB::transaction(function () use ($workspace, $customer, $amount, $type, $key, $source, $note, $by): RewardLedgerEntry {
            Customer::query()->where('workspace_id', $workspace)->lockForUpdate()->findOrFail($customer);
            if ($key && ($existing = RewardLedgerEntry::query()->where('workspace_id', $workspace)->where('idempotency_key', $key)->first())) {
                return $existing;
            }
            $balance = $this->balance($workspace, $customer) + $amount;
            abort_if($balance < 0, 422, 'Insufficient reward balance.');

            return RewardLedgerEntry::query()->create([
                'workspace_id' => $workspace,
                'customer_id' => $customer,
                'amount_minor' => $amount,
                'balance_after_minor' => $balance,
                'currency' => StoreOrderSetting::forWorkspace($workspace)->currency,
                'type' => $type,
                'idempotency_key' => $key,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'note' => $note,
                'created_by' => $by,
            ]);
        });
    }

    /**
     * The wallet amount that may pay for an order of the given payable amount.
     */
    public function redeemable(int $workspace, int $customer, int $payableMinor, int $precision): int
    {
        $settings = ReferralSetting::query()->where('workspace_id', $workspace)->first();
        $balance = $this->balance($workspace, $customer);
        if (! $settings || $balance <= 0 || $balance < OrderMoney::minor($settings->min_spend_balance, $precision)) {
            return 0;
        }

        return max(0, min($balance, intdiv($payableMinor * $settings->max_redeem_percent, 100)));
    }

    public function refundOrderRedemption(int $workspace, Model $order): void
    {
        $spent = RewardLedgerEntry::query()->where('workspace_id', $workspace)->where('idempotency_key', 'redeem:order:'.$order->getKey())->first();
        if ($spent) {
            $this->post($workspace, $spent->customer_id, -$spent->amount_minor, 'redeem_refund', 'redeem-refund:order:'.$order->getKey(), $order, 'Order cancelled');
        }
    }
}
