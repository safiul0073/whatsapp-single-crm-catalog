<?php

namespace App\Modules\PlansSubscriptions\Jobs;

use App\Enums\NotificationTemplateSlug;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Notifications\SubscriptionStatusNotification;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSubscriptionExpiryReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionAccessService $access): void
    {
        Subscription::query()
            ->with(['workspace.owner', 'plan'])
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->whereNotNull('renews_at')
            ->where('renews_at', '>', now())
            ->where('renews_at', '<=', now()->addDay())
            ->chunkById(100, function ($subscriptions) use ($access): void {
                foreach ($subscriptions as $subscription) {
                    if (! $access->expiresSoon($subscription) || $this->alreadySent($subscription)) {
                        continue;
                    }

                    $owner = $subscription->workspace?->owner;

                    if (! $owner) {
                        continue;
                    }

                    $owner->notify(new SubscriptionStatusNotification(
                        $subscription,
                        NotificationTemplateSlug::SUBSCRIPTION_EXPIRING_SOON,
                    ));
                }
            });
    }

    protected function alreadySent(Subscription $subscription): bool
    {
        return NotificationLog::query()
            ->where('template_slug', NotificationTemplateSlug::SUBSCRIPTION_EXPIRING_SOON->value)
            ->where('metadata->subscription_id', $subscription->id)
            ->where('metadata->expires_at', $subscription->renews_at?->toDateTimeString())
            ->exists();
    }
}
