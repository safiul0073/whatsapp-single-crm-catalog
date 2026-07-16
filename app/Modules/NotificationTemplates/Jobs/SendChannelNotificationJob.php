<?php

namespace App\Modules\NotificationTemplates\Jobs;

use App\Mail\GenericNotificationMail;
use App\Models\User;
use App\Modules\NotificationTemplates\Channels\Drivers\LogSmsDriver;
use App\Modules\NotificationTemplates\Channels\Drivers\SmsDriverInterface;
use App\Modules\NotificationTemplates\Channels\Drivers\TwilioSmsDriver;
use App\Modules\NotificationTemplates\Channels\Drivers\VonageSmsDriver;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendChannelNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $notificationLogId,
        public string $channel,
        public string $recipient,
        public ?string $subject,
        public string $body,
        public string $source
    ) {}

    public function handle(): void
    {
        $log = NotificationLog::query()->find($this->notificationLogId);

        if (! $log) {
            return;
        }

        try {
            if ($this->channel === 'email') {
                $this->sendEmail();
            } elseif ($this->channel === 'sms') {
                $this->resolveSmsDriver()->send($this->recipient, $this->body);
            }

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            if ($this->channel === 'email') {
                $this->notifyUserInApp($log);
            }
        } catch (\Throwable $exception) {
            $metadata = $log->metadata ?? [];
            $metadata['error'] = $exception->getMessage();

            $log->update([
                'status' => 'failed',
                'metadata' => $metadata,
            ]);

            report($exception);
        }
    }

    protected function sendEmail(): void
    {
        if (in_array($this->source, ['template', 'custom_html'], true)) {
            Mail::send('emails.template-notification', [
                'body' => $this->body,
                'actionUrl' => null,
                'actionText' => null,
            ], function ($message): void {
                $message->to($this->recipient)
                    ->subject($this->subject ?: setting('site_name', config('app.name', 'Admin Panel')));
            });

            return;
        }

        Mail::to($this->recipient)->send(
            new GenericNotificationMail(
                title: $this->subject ?: setting('site_name', config('app.name', 'Admin Panel')),
                body: $this->body,
            )
        );
    }

    protected function notifyUserInApp(NotificationLog $log): void
    {
        $notifiable = $log->notifiable;

        if (! $notifiable instanceof User) {
            return;
        }

        app(SystemNotificationService::class)->send($notifiable, [
            'title' => __('You have a new mail'),
            'body' => __('You received an email. Please check your mailbox.'),
            'icon' => 'mail',
            'url' => null,
            'type' => 'info',
        ]);
    }

    protected function resolveSmsDriver(): SmsDriverInterface
    {
        return match (setting('sms_provider', 'log')) {
            'vonage' => app(VonageSmsDriver::class),
            'twilio' => app(TwilioSmsDriver::class),
            default => app(LogSmsDriver::class),
        };
    }
}
