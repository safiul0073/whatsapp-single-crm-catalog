<?php

namespace App\Modules\NotificationTemplates\Notifications;

use App\Enums\NotificationTemplateSlug;
use App\Modules\NotificationTemplates\Channels\InAppChannel;
use App\Modules\NotificationTemplates\Channels\SmsChannel;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Services\TemplateRenderer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

abstract class BaseTemplateNotification extends Notification
{
    protected ?NotificationTemplate $template = null;

    protected ?TemplateRenderer $renderer = null;

    /**
     * The template slug identifying which template to use.
     */
    abstract protected function templateSlug(): NotificationTemplateSlug;

    /**
     * Variables to substitute into the template.
     *
     * @return array<string, string>
     */
    abstract protected function templateVariables(): array;

    /**
     * Get the template slug (public accessor for channels).
     */
    public function getTemplateSlug(): NotificationTemplateSlug
    {
        return $this->templateSlug();
    }

    /**
     * Determine which channels this notification should be sent through.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        $this->template = NotificationTemplate::findBySlug($this->templateSlug()->value);
        $this->renderer = app(TemplateRenderer::class);

        if (! $this->template || ! $this->template->is_active) {
            return [];
        }

        $enabledChannels = $this->template->getEnabledChannels();

        $channelMap = [
            'email' => 'mail',
            'in_app' => InAppChannel::class,
            'sms' => SmsChannel::class,
            'web_push' => WebPushChannel::class,
            'mobile_push' => FcmChannel::class,
        ];

        $channels = [];

        foreach ($enabledChannels as $channel) {
            if (! $this->notifiableSupportsChannel($notifiable, $channel)) {
                continue;
            }

            if (isset($channelMap[$channel])) {
                $channels[] = $channelMap[$channel];

                $this->createLogEntry($notifiable, $channel);
            }
        }

        return $channels;
    }

    /**
     * Build the mail representation.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->ensureTemplateLoaded();

        $vars = $this->resolveVariables($notifiable);
        $subject = $this->renderer->render($this->template->email_subject, $vars);
        $body = $this->renderer->render($this->template->email_body, $vars);

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.template-notification', [
                'body' => $body,
                'actionUrl' => $this->actionUrl(),
                'actionText' => $this->actionText(),
            ]);
    }

    /**
     * Build the in-app notification data.
     *
     * @return array{title: string, body: string, icon: string, type: string, url: ?string}
     */
    public function toInApp(object $notifiable): array
    {
        $this->ensureTemplateLoaded();

        $vars = $this->resolveVariables($notifiable);

        return [
            'title' => $this->renderer->render($this->template->in_app_title, $vars),
            'body' => $this->renderer->render($this->template->in_app_body, $vars),
            'icon' => $this->inAppIcon(),
            'type' => $this->inAppType(),
            'url' => $this->actionUrl(),
        ];
    }

    /**
     * Build the SMS message.
     */
    public function toSms(object $notifiable): string
    {
        $this->ensureTemplateLoaded();

        $vars = $this->resolveVariables($notifiable);

        return $this->renderer->render($this->template->sms_body, $vars);
    }

    /**
     * Build the Web Push (VAPID) notification message.
     */
    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        $this->ensureTemplateLoaded();

        $vars = $this->resolveVariables($notifiable);
        $title = $this->renderer->render($this->template->push_title, $vars);
        $body = $this->renderer->render($this->template->push_body, $vars);

        $message = (new WebPushMessage)
            ->title($title ?: '')
            ->body($body ?: '');

        if ($this->actionUrl()) {
            $message->data(['url' => $this->actionUrl()]);
        }

        return $message;
    }

    /**
     * Build the Firebase Cloud Messaging notification.
     */
    public function toFcm(object $notifiable): FcmMessage
    {
        $this->ensureTemplateLoaded();

        $vars = $this->resolveVariables($notifiable);
        $title = $this->renderer->render($this->template->push_title, $vars);
        $body = $this->renderer->render($this->template->push_body, $vars);

        $message = FcmMessage::create()
            ->notification(
                FcmNotification::create()
                    ->title($title ?: '')
                    ->body($body ?: '')
            );

        if ($this->actionUrl()) {
            $message->data(['url' => $this->actionUrl()]);
        }

        return $message;
    }

    /**
     * Merge notifiable-derived variables with template-specific variables.
     * Automatically provides user_name and user_email from the notifiable.
     *
     * @return array<string, string>
     */
    protected function resolveVariables(object $notifiable): array
    {
        $notifiableVars = [];

        if (isset($notifiable->name)) {
            $notifiableVars['user_name'] = $notifiable->name;
        }

        if (isset($notifiable->email)) {
            $notifiableVars['user_email'] = $notifiable->email;
        }

        return array_merge($notifiableVars, $this->templateVariables());
    }

    /**
     * Optional action URL for email CTA button and in-app link.
     */
    protected function actionUrl(): ?string
    {
        return null;
    }

    /**
     * Optional action button text for email CTA.
     */
    protected function actionText(): ?string
    {
        return null;
    }

    /**
     * Phosphor icon class for the in-app notification.
     */
    protected function inAppIcon(): string
    {
        return 'ph-bell';
    }

    /**
     * Notification type for in-app styling (info, success, warning, danger).
     */
    protected function inAppType(): string
    {
        return 'info';
    }

    /**
     * Check if the notifiable entity supports a given channel.
     */
    protected function notifiableSupportsChannel(object $notifiable, string $channel): bool
    {
        return match ($channel) {
            'email' => ! empty($notifiable->email),
            'sms' => ! empty($notifiable->phone),
            'in_app' => true,
            'web_push' => method_exists($notifiable, 'pushSubscriptions'),
            'mobile_push' => method_exists($notifiable, 'deviceTokens')
                && $notifiable->deviceTokens()->exists(),
            default => false,
        };
    }

    /**
     * Create a log entry for the notification dispatch.
     */
    protected function createLogEntry(object $notifiable, string $channel): void
    {
        NotificationLog::create([
            'template_slug' => $this->templateSlug()->value,
            'channel' => $channel,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'status' => 'queued',
        ]);
    }

    /**
     * Ensure the template and renderer are loaded (for channel methods called outside via()).
     */
    protected function ensureTemplateLoaded(): void
    {
        if (! $this->template) {
            $this->template = NotificationTemplate::findBySlug($this->templateSlug()->value);
        }

        if (! $this->renderer) {
            $this->renderer = app(TemplateRenderer::class);
        }
    }
}
