<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\Referral;
use App\Modules\Commerce\Storefront\Models\ReferralCode;
use App\Modules\Commerce\Storefront\Models\ReferralCustomerRule;
use App\Modules\Commerce\Storefront\Models\ReferralSetting;
use App\Modules\Commerce\Storefront\Models\ReferralTargetSlab;
use App\Modules\Commerce\Storefront\Models\ReferralTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralService
{
    public function __construct(private RewardWalletService $wallet) {}

    public function settings(int $workspace): ReferralSetting
    {
        return ReferralSetting::query()->firstOrNew(['workspace_id' => $workspace], [
            'enabled' => false,
            'reward_trigger' => 'delivered_order',
            'reward_scope' => 'first_order',
            'reward_type' => 'fixed',
            'reward_value' => 0,
            'min_order_amount' => 0,
            'referee_discount_type' => 'none',
            'referee_discount_value' => 0,
            'referee_min_order' => 0,
            'max_redeem_percent' => 100,
            'min_spend_balance' => 0,
            'cashin_enabled' => false,
            'min_cashin_balance' => 0,
            'cashin_methods' => [],
        ]);
    }

    public function codeFor(Customer $customer): ReferralCode
    {
        $existing = ReferralCode::query()->where('workspace_id', $customer->workspace_id)->where('customer_id', $customer->id)->first();
        if ($existing) {
            return $existing;
        }
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', Str::ascii((string) $customer->contact?->name)) ?: 'FRIEND', 0, 6));
        do {
            $code = $base.random_int(1000, 9999);
        } while (ReferralCode::query()->where('workspace_id', $customer->workspace_id)->where('code', $code)->exists());

        return ReferralCode::query()->firstOrCreate(['workspace_id' => $customer->workspace_id, 'customer_id' => $customer->id], ['code' => $code]);
    }

    /**
     * Link a newly registered customer to the owner of the referral code.
     */
    public function attach(Customer $referee, ?string $code): ?Referral
    {
        $settings = $this->settings($referee->workspace_id);
        $code = Str::upper(trim((string) $code));
        if (! $settings->enabled || $code === '') {
            return null;
        }

        return DB::transaction(function () use ($referee, $code, $settings): ?Referral {
            $owner = ReferralCode::query()->where('workspace_id', $referee->workspace_id)->where('code', $code)->first();
            if (! $owner || $owner->customer_id === $referee->id || Referral::query()->where('workspace_id', $referee->workspace_id)->where('referee_customer_id', $referee->id)->exists()) {
                return null;
            }
            if ($settings->max_referrals_per_customer !== null && Referral::query()->where('workspace_id', $referee->workspace_id)->where('referrer_customer_id', $owner->customer_id)->count() >= $settings->max_referrals_per_customer) {
                return null;
            }
            $referral = Referral::query()->create(['workspace_id' => $referee->workspace_id, 'referrer_customer_id' => $owner->customer_id, 'referee_customer_id' => $referee->id, 'code' => $code, 'status' => 'active']);
            if ($settings->reward_trigger === 'signup') {
                $store = StoreOrderSetting::forWorkspace($referee->workspace_id);
                $reward = $this->reward($settings, $this->rule($owner->customer_id, $referee->workspace_id), 0, $store->precision());
                $transaction = ReferralTransaction::query()->create(['workspace_id' => $referee->workspace_id, 'referral_id' => $referral->id, 'reward_minor' => $reward, 'currency' => $store->currency, 'status' => 'pending']);
                $this->credit($transaction);
            }

            return $referral;
        });
    }

    /**
     * Welcome discount for a referred customer's first order, in minor units.
     */
    public function welcomeDiscount(Customer $customer, int $subtotalMinor, int $precision): int
    {
        $settings = $this->settings($customer->workspace_id);
        if (! $settings->enabled || $settings->referee_discount_type === 'none' || $subtotalMinor < OrderMoney::minor($settings->referee_min_order, $precision)) {
            return 0;
        }
        if (! Referral::query()->where('workspace_id', $customer->workspace_id)->where('referee_customer_id', $customer->id)->exists()) {
            return 0;
        }
        if (Order::query()->where('workspace_id', $customer->workspace_id)->where('contact_id', $customer->contact_id)->where('status', '!=', 'cancelled')->exists()) {
            return 0;
        }
        $discount = $settings->referee_discount_type === 'percent'
            ? intdiv($subtotalMinor * (int) round((float) $settings->referee_discount_value * 100), 10000)
            : OrderMoney::minor($settings->referee_discount_value, $precision);

        return max(0, min($subtotalMinor, $discount));
    }

    /**
     * Record a pending referrer reward for an order placed by a referred customer.
     */
    public function orderPlaced(Order $order, Customer $customer): ?ReferralTransaction
    {
        $settings = $this->settings($customer->workspace_id);
        $referral = Referral::query()->where('workspace_id', $customer->workspace_id)->where('referee_customer_id', $customer->id)->where('status', 'active')->first();
        if (! $settings->enabled || $settings->reward_trigger === 'signup' || ! $referral) {
            return null;
        }
        if ($settings->reward_scope === 'first_order' && $referral->transactions()->where('status', '!=', 'cancelled')->exists()) {
            return null;
        }
        $store = StoreOrderSetting::forWorkspace($customer->workspace_id);
        $precision = $store->precision();
        $amount = OrderMoney::minor($order->subtotal, $precision);
        $rule = $this->rule($referral->referrer_customer_id, $customer->workspace_id);
        if ($amount < OrderMoney::minor($rule?->min_order_amount ?? $settings->min_order_amount, $precision)) {
            return null;
        }

        return ReferralTransaction::query()->firstOrCreate(['referral_id' => $referral->id, 'order_id' => $order->id], [
            'workspace_id' => $customer->workspace_id,
            'order_amount_minor' => $amount,
            'reward_minor' => $this->reward($settings, $rule, $amount, $precision),
            'currency' => $store->currency,
            'status' => 'pending',
        ]);
    }

    public function orderStatusChanged(Order $order): void
    {
        if ($order->status === 'cancelled') {
            $this->wallet->refundOrderRedemption($order->workspace_id, $order);
            ReferralTransaction::query()->where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
        }
        if ($order->status === 'completed') {
            ReferralTransaction::query()->where('order_id', $order->id)->where('status', 'pending')->get()->each(fn (ReferralTransaction $t) => $this->credit($t));
        }
    }

    public function credit(ReferralTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $locked = ReferralTransaction::query()->with('referral')->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status !== 'pending') {
                return;
            }
            if ($locked->reward_minor > 0) {
                $this->wallet->post($locked->workspace_id, $locked->referral->referrer_customer_id, $locked->reward_minor, 'referral_reward', 'referral:'.$locked->id, $locked, 'Referral reward');
            }
            $locked->update(['status' => 'credited', 'credited_at' => now()]);
        });
    }

    /**
     * Credit target slab bonuses for the period that has just ended.
     */
    public function awardTargetSlabs(?Carbon $now = null): int
    {
        $now ??= now();
        $awarded = 0;
        ReferralTargetSlab::query()->where('active', true)->get()->each(function (ReferralTargetSlab $slab) use ($now, &$awarded): void {
            $settings = $this->settings($slab->workspace_id);
            if (! $settings->enabled) {
                return;
            }
            [$from, $to, $key] = match ($slab->period) {
                'daily' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), $now->copy()->subDay()->format('Y-m-d')],
                'weekly' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek(), $now->copy()->subWeek()->format('o-\WW')],
                default => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), $now->copy()->subMonthNoOverflow()->format('Y-m')],
            };
            $reward = OrderMoney::minor($slab->reward_amount, StoreOrderSetting::forWorkspace($slab->workspace_id)->precision());
            ReferralTransaction::query()
                ->join('commerce_referrals', 'commerce_referrals.id', '=', 'commerce_referral_transactions.referral_id')
                ->where('commerce_referral_transactions.workspace_id', $slab->workspace_id)
                ->where('commerce_referral_transactions.status', 'credited')
                ->whereBetween('commerce_referral_transactions.credited_at', [$from, $to])
                ->groupBy('commerce_referrals.referrer_customer_id')
                ->havingRaw('COUNT(*) >= ?', [$slab->min_referrals])
                ->pluck('commerce_referrals.referrer_customer_id')
                ->each(function (int $customer) use ($slab, $reward, $key, &$awarded): void {
                    $entry = $this->wallet->post($slab->workspace_id, $customer, $reward, 'target_bonus', "slab:{$slab->id}:{$customer}:{$key}", $slab, $slab->name);
                    $awarded += $entry->wasRecentlyCreated ? 1 : 0;
                });
        });

        return $awarded;
    }

    public function rule(int $customer, int $workspace): ?ReferralCustomerRule
    {
        return ReferralCustomerRule::query()->where('workspace_id', $workspace)->where('customer_id', $customer)->where('active', true)->first();
    }

    private function reward(ReferralSetting $settings, ?ReferralCustomerRule $rule, int $orderMinor, int $precision): int
    {
        $type = $rule?->reward_type ?? $settings->reward_type;
        $value = $rule?->reward_value ?? $settings->reward_value;
        $cap = $rule ? $rule->reward_cap : $settings->reward_cap;
        if ($type === 'fixed') {
            return OrderMoney::minor($value, $precision);
        }
        $reward = intdiv($orderMinor * (int) round((float) $value * 100), 10000);

        return $type === 'percent_capped' && $cap !== null ? min($reward, OrderMoney::minor($cap, $precision)) : $reward;
    }
}
