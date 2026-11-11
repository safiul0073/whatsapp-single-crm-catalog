<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;

/**
 * Credits the unused part of the current paid period against an upgrade. All math is in minor units.
 */
class ProrationService
{
    public function __construct(protected SubscriptionAccessService $access) {}

    /**
     * @return array{plan_price: string, credit: string, amount_due: string, unused_ratio: float}
     */
    public function quote(?Subscription $subscription, Plan $target): array
    {
        $targetMinor = OrderMoney::minor((string) $target->price);
        $creditMinor = min($targetMinor, $this->creditMinor($subscription));

        return [
            'plan_price' => OrderMoney::decimal($targetMinor),
            'credit' => OrderMoney::decimal($creditMinor),
            'amount_due' => OrderMoney::decimal(max(0, $targetMinor - $creditMinor)),
            'unused_ratio' => $this->unusedRatio($subscription),
        ];
    }

    public function creditMinor(?Subscription $subscription): int
    {
        $plan = $subscription?->plan;

        if (! $subscription || ! $plan || $plan->neverExpires() || ! $this->access->subscriptionIsUsable($subscription)) {
            return 0;
        }

        $paidMinor = OrderMoney::minor((string) $plan->price);

        return intdiv($paidMinor * $this->unusedPermille($subscription), 1000);
    }

    public function unusedRatio(?Subscription $subscription): float
    {
        return $this->unusedPermille($subscription) / 1000;
    }

    /**
     * Unused share of the current period in thousandths, so the credit stays integer math.
     */
    protected function unusedPermille(?Subscription $subscription): int
    {
        $plan = $subscription?->plan;
        $renewsAt = $subscription?->renews_at;

        if (! $plan || ! $renewsAt || $renewsAt->isPast()) {
            return 0;
        }

        $periodStart = $plan->interval === Plan::INTERVAL_YEAR
            ? $renewsAt->copy()->subYearNoOverflow()
            : $renewsAt->copy()->subMonthNoOverflow();
        $periodSeconds = max(1, $periodStart->diffInSeconds($renewsAt));
        $remainingSeconds = (int) min($periodSeconds, now()->diffInSeconds($renewsAt));

        return (int) floor($remainingSeconds * 1000 / $periodSeconds);
    }
}
