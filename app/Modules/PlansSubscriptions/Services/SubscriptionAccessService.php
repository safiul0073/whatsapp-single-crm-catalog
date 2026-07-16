<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Subscription;
use Illuminate\Support\Carbon;

class SubscriptionAccessService
{
    public const EXPIRED_MESSAGE = 'Your subscription plan has expired. Please renew your plan to continue using services.';

    public function currentForWorkspace(int $workspaceId): ?Subscription
    {
        return null;
    }

    public function isActiveForUse(int $workspaceId): bool
    {
        return true;
    }

    public function isExpiredForUse(int $workspaceId): bool
    {
        return false;
    }

    public function canUseServices(int $workspaceId): bool
    {
        return true;
    }

    public function assertActiveForUse(int $workspaceId): void {}

    public function shouldExpire(Subscription $subscription): bool
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)) {
            return false;
        }

        return $this->dateHasPassed($subscription->renews_at)
            || $this->dateHasPassed($subscription->ends_at);
    }

    public function expiresSoon(Subscription $subscription, int $days = 1): bool
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)) {
            return false;
        }

        if (! $subscription->renews_at || $this->dateHasPassed($subscription->renews_at)) {
            return false;
        }

        return $subscription->renews_at->lte(now()->addDays($days));
    }

    public function expiryDate(Subscription $subscription): ?Carbon
    {
        if ($subscription->ends_at && $subscription->renews_at) {
            return $subscription->ends_at->lessThan($subscription->renews_at)
                ? $subscription->ends_at
                : $subscription->renews_at;
        }

        return $subscription->ends_at ?? $subscription->renews_at;
    }

    protected function dateHasPassed(?Carbon $date): bool
    {
        return $date !== null && $date->lte(now());
    }
}
