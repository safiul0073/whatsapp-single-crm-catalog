<?php

namespace App\Modules\NotificationTemplates\Listeners;

use App\Modules\NotificationTemplates\Channels\InAppChannel;
use App\Modules\NotificationTemplates\Channels\SmsChannel;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Notifications\BaseTemplateNotification;
use Illuminate\Notifications\Events\NotificationSent;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\WebPush\WebPushChannel;

class LogNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        if (! $event->notification instanceof BaseTemplateNotification) {
            return;
        }

        $channel = $this->resolveChannelName($event->channel);

        NotificationLog::where('template_slug', $event->notification->getTemplateSlug()->value)
            ->where('channel', $channel)
            ->where('notifiable_type', $event->notifiable->getMorphClass())
            ->where('notifiable_id', $event->notifiable->getKey())
            ->where('status', 'queued')
            ->latest()
            ->first()
            ?->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
    }

    /**
     * Map Laravel channel class names to our channel names.
     */
    protected function resolveChannelName(string $channel): string
    {
        return match ($channel) {
            'mail' => 'email',
            WebPushChannel::class => 'web_push',
            FcmChannel::class => 'mobile_push',
            InAppChannel::class => 'in_app',
            SmsChannel::class => 'sms',
            default => $channel,
        };
    }
}
