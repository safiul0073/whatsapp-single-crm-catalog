<?php

namespace App\Modules\PlansSubscriptions\Notifications;

use App\Enums\NotificationTemplateSlug;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Notifications\BaseTemplateNotification;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;

class SubscriptionUsageNotification extends BaseTemplateNotification
{
    public function __construct(
        protected Subscription $subscription,
        protected NotificationTemplateSlug $slug,
        protected string $limitKey,
        protected int $threshold,
        protected int $used,
        protected int $limit,
    ) {}

    protected function templateSlug(): NotificationTemplateSlug
    {
        return $this->slug;
    }

    protected function templateVariables(): array
    {
        $this->subscription->loadMissing(['plan', 'workspace']);

        return [
            'workspace_name' => (string) ($this->subscription->workspace?->name ?? ''),
            'plan_name' => (string) ($this->subscription->plan?->name ?? ''),
            'limit_name' => PlanLimitService::label($this->limitKey),
            'used' => (string) $this->used,
            'limit' => (string) $this->limit,
            'percent' => (string) $this->threshold,
            'upgrade_url' => route('user.subscription.show'),
        ];
    }

    protected function actionUrl(): ?string
    {
        return route('user.subscription.show');
    }

    protected function actionText(): ?string
    {
        return __('Upgrade plan');
    }

    protected function inAppIcon(): string
    {
        return 'ph-gauge';
    }

    protected function inAppType(): string
    {
        return $this->threshold >= 100 ? 'error' : 'warning';
    }

    protected function createLogEntry(object $notifiable, string $channel): void
    {
        NotificationLog::create([
            'template_slug' => $this->templateSlug()->value,
            'channel' => $channel,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'status' => 'queued',
            'metadata' => [
                'subscription_id' => $this->subscription->id,
                'workspace_id' => $this->subscription->workspace_id,
                'limit_key' => $this->limitKey,
                'threshold' => $this->threshold,
            ],
        ]);
    }
}
