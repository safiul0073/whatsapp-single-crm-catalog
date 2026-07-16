<?php

namespace App\Modules\ContactMessages\Services;

use App\Models\Admin;
use App\Modules\ContactMessages\Models\ContactMessage;
use App\Modules\ContactMessages\Models\ContactMessageReply;
use App\Modules\NotificationTemplates\Jobs\SendChannelNotificationJob;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Services\TemplateRenderer;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ContactMessageReplyService
{
    public function __construct(
        protected TemplateRenderer $renderer
    ) {}

    public function send(ContactMessage $message, array $payload, ?Admin $admin = null): ContactMessageReply
    {
        $this->ensureEmailEnabled();

        $template = $this->resolveTemplate(Arr::get($payload, 'template_id'), Arr::get($payload, 'reply_type'));
        $content = $this->composeContent($message, $template, $payload);
        $variables = $this->buildVariables($message, Arr::get($payload, 'template_variables', []));
        $log = $this->createLog($message, $template, $content);

        SendChannelNotificationJob::dispatch(
            notificationLogId: $log->id,
            channel: 'email',
            recipient: $message->email,
            subject: $content['subject'],
            body: $content['body'],
            source: $content['source'],
        );

        $reply = ContactMessageReply::query()->create([
            'contact_message_id' => $message->id,
            'admin_id' => $admin?->id,
            'notification_log_id' => $log->id,
            'source' => $content['source'] === 'custom_html' ? 'custom' : $content['source'],
            'template_slug' => $template?->slug,
            'recipient_email' => $message->email,
            'subject' => $content['subject'],
            'body' => $content['body'],
            'template_variables' => $variables,
            'queued_at' => now(),
        ]);

        if ($message->status === ContactMessage::STATUS_NEW) {
            $message->update([
                'status' => ContactMessage::STATUS_READ,
                'read_at' => now(),
            ]);
        }

        return $reply;
    }

    protected function ensureEmailEnabled(): void
    {
        if (! (bool) setting('enable_email_notifications', true)) {
            throw ValidationException::withMessages([
                'reply_type' => __('Email notifications are currently disabled in notification settings.'),
            ]);
        }
    }

    protected function resolveTemplate(mixed $templateId, ?string $replyType): ?NotificationTemplate
    {
        if ($replyType !== 'template') {
            return null;
        }

        $template = NotificationTemplate::query()
            ->active()
            ->find($templateId);

        if (! $template) {
            throw ValidationException::withMessages([
                'template_id' => __('The selected template is not available.'),
            ]);
        }

        if (! in_array('email', $template->getEnabledChannels(), true)) {
            throw ValidationException::withMessages([
                'template_id' => __('The selected template does not support email delivery.'),
            ]);
        }

        if (! $template->email_subject || ! $template->email_body) {
            throw ValidationException::withMessages([
                'template_id' => __('The selected template is missing email content.'),
            ]);
        }

        return $template;
    }

    /**
     * @return array{source: string, subject: string, body: string}
     */
    protected function composeContent(ContactMessage $message, ?NotificationTemplate $template, array $payload): array
    {
        $variables = $this->buildVariables($message, Arr::get($payload, 'template_variables', []));

        if ($template) {
            return [
                'source' => 'template',
                'subject' => $this->renderer->render($template->email_subject, $variables),
                'body' => $this->renderer->render($template->email_body, $variables),
            ];
        }

        return [
            'source' => 'custom_html',
            'subject' => $this->renderer->render((string) Arr::get($payload, 'subject', ''), $variables),
            'body' => $this->renderer->render((string) Arr::get($payload, 'body', ''), $variables),
        ];
    }

    /**
     * @param  array<string, mixed>  $templateVariables
     * @return array<string, string>
     */
    protected function buildVariables(ContactMessage $message, array $templateVariables): array
    {
        $contactVariables = [
            'name' => $message->full_name,
            'first_name' => $message->first_name,
            'last_name' => $message->last_name,
            'email' => $message->email,
            'company' => $message->company,
            'interest' => $message->interest,
            'message' => $message->message,
        ];

        $templateVariables = collect($templateVariables)
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (string) $value])
            ->all();

        return array_filter(
            array_merge($contactVariables, $templateVariables),
            static fn ($value): bool => $value !== ''
        );
    }

    /**
     * @param  array{source: string, subject: string, body: string}  $content
     */
    protected function createLog(ContactMessage $message, ?NotificationTemplate $template, array $content): NotificationLog
    {
        return NotificationLog::query()->create([
            'template_slug' => $template?->slug,
            'channel' => 'email',
            'notifiable_type' => $message->getMorphClass(),
            'notifiable_id' => $message->getKey(),
            'status' => 'queued',
            'metadata' => [
                'source' => $content['source'],
                'template_name' => $template?->name,
                'recipient_name' => $message->full_name,
                'recipient_address' => $message->email,
                'subject' => $content['subject'],
                'body' => $content['body'],
            ],
        ]);
    }
}
