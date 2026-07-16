<?php

namespace App\Modules\NotificationTemplates\Notifications;

use App\Enums\NotificationTemplateSlug;

class SendAutoNotification extends BaseTemplateNotification
{
    public function __construct(
        public NotificationTemplateSlug $templateSlug,
    ) {}

    protected function templateSlug(): NotificationTemplateSlug
    {
        return $this->templateSlug;
    }

    /**
     * @return array<string, string>
     */
    protected function templateVariables(): array
    {
        return [
            'changed_at' => now()->format('M d, Y \a\t H:i'),
        ];
    }

    protected function inAppIcon(): string
    {
        return 'ph-lock-key';
    }

    protected function inAppType(): string
    {
        return 'warning';
    }
}
