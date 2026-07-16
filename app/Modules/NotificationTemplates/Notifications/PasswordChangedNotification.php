<?php

namespace App\Modules\NotificationTemplates\Notifications;

use App\Enums\NotificationTemplateSlug;

class PasswordChangedNotification extends BaseTemplateNotification
{
    public function __construct() {}

    protected function templateSlug(): NotificationTemplateSlug
    {
        return NotificationTemplateSlug::PASSWORD_CHANGED;
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
