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

class ExpireSubscriptionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionAccessService $access): void
    {
        Subscription::query()
            ->with(['workspace.owner', 'plan'])
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->whereNotNull('renews_at')->where('renews_at', '<=', now()))
                    ->orWhere(fn ($q) => $q->whereNotNull('ends_at')->where('ends_at', '<=', now()));
            })
            ->chunkById(100, function ($subscriptions) use ($access): void {
                foreach ($subscriptions as $subscription) {
                    if (! $access->shouldExpire($subscription)) {
                        continue;
                    }

                    $expiresAt = $access->expiryDate($subscription) ?? now();

                    $subscription->forceFill([
                        'status' => SubscriptionStatus::Expired,
                        'ends_at' => $subscription->ends_at ?? $expiresAt,
                    ])->save();

                    if ($this->alreadySent($subscription, $expiresAt)) {
                        continue;
                    }

                    $owner = $subscription->workspace?->owner;

                    if (! $owner) {
                        continue;
                    }

                    $owner->notify(new SubscriptionStatusNotification(
                        $subscription->fresh(['workspace.owner', 'plan']),
                        NotificationTemplateSlug::SUBSCRIPTION_EXPIRED,
                    ));
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
