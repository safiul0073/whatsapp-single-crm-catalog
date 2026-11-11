<?php

namespace App\Modules\PlansSubscriptions\Jobs;

use App\Enums\NotificationTemplateSlug;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Notifications\SubscriptionStatusNotification;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use App\Modules\PlansSubscriptions\Services\SubscriptionManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;

class ExpireSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * The scheduler still runs this hourly when the Plans & Subscriptions module is disabled and its tables
     * were never created; exit quietly instead of failing every run.
     */
    public function handle(SubscriptionAccessService $access, ?SubscriptionManager $manager = null): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        $manager ??= app(SubscriptionManager::class);

        Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->whereNotNull('renews_at')->where('renews_at', '<=', now()))
                    ->orWhere(fn ($q) => $q->whereNotNull('ends_at')->where('ends_at', '<=', now()));
            })
            ->chunkById(100, function ($subscriptions) use ($access, $manager): void {
                foreach ($subscriptions as $subscription) {
                    $expiresAt = $access->expiryDate($subscription) ?? now();

                    if (! $manager->closePeriod($subscription)) {
                        continue;
                    }

                    $subscription = $subscription->fresh(['workspace.owner', 'plan']);

                    if ($this->alreadySent($subscription, $expiresAt)) {
                        continue;
                    }

                    $owner = $subscription->workspace?->owner;

                    if (! $owner) {
                        continue;
                    }

                    $owner->notify(new SubscriptionStatusNotification($subscription, NotificationTemplateSlug::SUBSCRIPTION_EXPIRED));
                }
            });
    }

    protected function alreadySent(Subscription $subscription, $expiresAt): bool
    {
        return NotificationLog::query()
            ->where('template_slug', NotificationTemplateSlug::SUBSCRIPTION_EXPIRED->value)
            ->where('metadata->subscription_id', $subscription->id)
            ->where('metadata->expires_at', $expiresAt?->toDateTimeString())
            ->exists();
    }
}
