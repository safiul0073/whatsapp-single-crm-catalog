<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Exceptions\SubscriptionLimitException;
use App\Modules\PlansSubscriptions\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SubscriptionAccessService
{
    public const EXPIRED_MESSAGE = 'Your subscription plan has expired. Please renew your plan to continue using services.';

    /**
     * Plans are enforced only once the module's tables exist (same rule the scheduler jobs use),
     * so a store database without the subscription tables keeps working unrestricted.
     */
    public function enforced(): bool
    {
        $connection = Subscription::query()->getConnection();
        $key = 'plans-subscriptions.installed.'.$connection->getName().'.'.$connection->getDatabaseName();

        if (! app()->bound($key)) {
            app()->instance($key, Schema::connection($connection->getName())->hasTable('subscriptions'));
        }

        return (bool) app($key);
    }

    public function currentForWorkspace(int $workspaceId): ?Subscription
    {
        if (! $this->enforced()) {
            return null;
        }

        return Subscription::query()
            ->with('plan')
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    public function isActiveForUse(int $workspaceId): bool
    {
        if (! $this->enforced()) {
            return true;
        }

        $subscription = $this->currentForWorkspace($workspaceId);

        return $subscription !== null && $this->subscriptionIsUsable($subscription);
    }

    /**
     * A workspace counts as expired only when it had a subscription that is no longer usable.
     */
    public function isExpiredForUse(int $workspaceId): bool
    {
        $subscription = $this->currentForWorkspace($workspaceId);

        return $subscription !== null && ! $this->subscriptionIsUsable($subscription);
    }

    public function canUseServices(int $workspaceId): bool
    {
        return $this->isActiveForUse($workspaceId);
    }

    public function assertActiveForUse(int $workspaceId): void
    {
        if (! $this->canUseServices($workspaceId)) {
            throw new SubscriptionLimitException(__(self::EXPIRED_MESSAGE));
        }
    }

    public function subscriptionIsUsable(Subscription $subscription): bool
    {
        if (! $subscription->plan_id || ! $subscription->hasUsableStatus()) {
            return false;
        }

        $expiresAt = $this->expiryDate($subscription);

        return $expiresAt === null || ! $this->dateHasPassed($expiresAt);
    }

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
