<?php

namespace App\Modules\NotificationTemplates\Channels;

use App\Modules\NotificationTemplates\Channels\Drivers\LogSmsDriver;
use App\Modules\NotificationTemplates\Channels\Drivers\SmsDriverInterface;
use App\Modules\NotificationTemplates\Channels\Drivers\TwilioSmsDriver;
use App\Modules\NotificationTemplates\Channels\Drivers\VonageSmsDriver;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Notifications\BaseTemplateNotification;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    /**
     * Send the given notification via SMS.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof BaseTemplateNotification) {
            return;
        }

        $message = $notification->toSms($notifiable);
        $phone = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms($notification)
            : ($notifiable->phone ?? null);

        if (! $phone || ! $message) {
            return;
        }

        $driver = $this->resolveDriver();

        try {
            $driver->send($phone, $message);

            NotificationLog::where('template_slug', $notification->getTemplateSlug())
                ->where('channel', 'sms')
                ->where('notifiable_type', $notifiable->getMorphClass())
                ->where('notifiable_id', $notifiable->getKey())
                ->where('status', 'queued')
                ->latest()
                ->first()
                ?->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            NotificationLog::where('template_slug', $notification->getTemplateSlug())
                ->where('channel', 'sms')
                ->where('notifiable_type', $notifiable->getMorphClass())
                ->where('notifiable_id', $notifiable->getKey())
                ->where('status', 'queued')
                ->latest()
                ->first()
                ?->update([
                    'status' => 'failed',
                    'metadata' => ['error' => $e->getMessage()],
                ]);

            report($e);
        }
    }

    /**
     * Resolve the SMS driver based on settings.
     */
    protected function resolveDriver(): SmsDriverInterface
    {
        $provider = setting('sms_provider', 'log');

        return match ($provider) {
            'vonage' => app(VonageSmsDriver::class),
            'twilio' => app(TwilioSmsDriver::class),
            default => app(LogSmsDriver::class),
        };
    }
}
