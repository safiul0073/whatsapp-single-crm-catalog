<?php

namespace App\Modules\NotificationTemplates\Channels;

use App\Modules\NotificationTemplates\Notifications\BaseTemplateNotification;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use Illuminate\Notifications\Notification;

class InAppChannel
{
    public function __construct(
        protected SystemNotificationService $systemNotificationService
    ) {}

    /**
     * Send the given notification via the in-app system notifications.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof BaseTemplateNotification) {
            return;
        }

        $data = $notification->toInApp($notifiable);

        $this->systemNotificationService->send(
            $notifiable,
            $data,
            $notification->getTemplateSlug()->value
        );
    }
}
