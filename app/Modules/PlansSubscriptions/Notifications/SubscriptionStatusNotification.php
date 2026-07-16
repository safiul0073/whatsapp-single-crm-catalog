<?php

namespace App\Modules\PlansSubscriptions\Notifications;

use App\Enums\NotificationTemplateSlug;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Notifications\BaseTemplateNotification;
use App\Modules\PlansSubscriptions\Models\Subscription;

class SubscriptionStatusNotification extends BaseTemplateNotification
{
    public function __construct(
        protected Subscription $subscription,
        protected NotificationTemplateSlug $slug,
    ) {}

    protected function templateSlug(): NotificationTemplateSlug
    {
        return $this->slug;
    }

    protected function templateVariables(): array
    {
        $this->subscription->loadMissing(['plan', 'workspace']);
        $expiresAt = $this->subscription->ends_at ?? $this->subscription->renews_at;
        $daysRemaining = $expiresAt ? max(0, (int) ceil(now()->diffInDays($expiresAt, false))) : 0;

        return [
            'workspace_name' => (string) ($this->subscription->workspace?->name ?? ''),
            'plan_name' => (string) ($this->subscription->plan?->name ?? ''),
            'expires_at' => $expiresAt?->format('M d, Y') ?? '',
            'renew_url' => route('user.subscription.show'),
            'days_remaining' => (string) $daysRemaining,
        ];
    }

    protected function actionUrl(): ?string
    {
        return route('user.subscription.show');
    }

    protected function actionText(): ?string
    {
        return __('Renew plan');
    }

    protected function inAppIcon(): string
    {
        return $this->slug === NotificationTemplateSlug::SUBSCRIPTION_EXPIRED
            ? 'ph-warning-circle'
            : 'ph-clock-countdown';
    }

    protected function inAppType(): string
    {
        return 'warning';
    }

    protected function createLogEntry(object $notifiable, string $channel): void
    {
        $expiresAt = $this->subscription->ends_at ?? $this->subscription->renews_at;

        NotificationLog::create([
            'template_slug' => $this->templateSlug()->value,
            'channel' => $channel,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'status' => 'queued',
            'metadata' => [
                'subscription_id' => $this->subscription->id,
                'workspace_id' => $this->subscription->workspace_id,
                'plan_id' => $this->subscription->plan_id,
                'expires_at' => $expiresAt?->toDateTimeString(),
            ],
        ]);
    }
}
