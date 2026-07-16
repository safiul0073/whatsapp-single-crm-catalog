<?php

namespace App\Modules\NotificationTemplates\Services;

use App\Modules\NotificationTemplates\Jobs\SendChannelNotificationJob;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class NotificationDispatchService
{
    public function __construct(
        protected NotificationRecipientResolver $recipientResolver,
        protected TemplateRenderer $renderer
    ) {}

    public function dispatch(array $payload): int
    {
        $channel = $payload['channel'];

        $this->ensureChannelEnabled($channel);

        $template = $this->resolveTemplate(
            templateId: Arr::get($payload, 'template_id'),
            channel: $channel,
        );

        $recipients = $this->recipientResolver->resolve(
            recipientType: $payload['recipient_type'],
            roleId: Arr::get($payload, 'role_id'),
        );

        $queuedCount = 0;

        foreach ($recipients as $recipient) {
            $address = $this->resolveAddress($recipient, $channel);

            if (! $address) {
                continue;
            }

            $content = $this->composeContent($recipient, $channel, $template, $payload);
            $log = $this->createLog($recipient, $channel, $template, $address, $content);

            SendChannelNotificationJob::dispatch(
                notificationLogId: $log->id,
                channel: $channel,
                recipient: $address,
                subject: $content['subject'],
                body: $content['body'],
                source: $content['source'],
            );

            $queuedCount++;
        }

        if ($queuedCount === 0) {
            throw ValidationException::withMessages([
                'recipient_type' => __('No active recipients are available for the selected channel.'),
            ]);
        }

        return $queuedCount;
    }

    protected function ensureChannelEnabled(string $channel): void
    {
        $enabled = match ($channel) {
            'email' => (bool) setting('enable_email_notifications', true),
            'sms' => (bool) setting('enable_sms_notifications', false),
            default => false,
        };

        if (! $enabled) {
            throw ValidationException::withMessages([
                'channel' => __('The selected channel is currently disabled in notification settings.'),
            ]);
        }
    }

    protected function resolveTemplate(mixed $templateId, string $channel): ?NotificationTemplate
    {
        if (! $templateId) {
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

        if (! in_array($channel, $template->getEnabledChannels(), true)) {
            throw ValidationException::withMessages([
                'template_id' => __('The selected template does not support the :channel channel.', ['channel' => strtoupper($channel)]),
            ]);
        }

        if ($channel === 'email' && (! $template->email_subject || ! $template->email_body)) {
            throw ValidationException::withMessages([
                'template_id' => __('The selected template is missing email content.'),
            ]);
        }

        if ($channel === 'sms' && ! $template->sms_body) {
            throw ValidationException::withMessages([
                'template_id' => __('The selected template is missing SMS content.'),
            ]);
        }

        return $template;
    }

    protected function resolveAddress(Model $recipient, string $channel): ?string
    {
        return match ($channel) {
            'email' => $recipient->email ?: null,
            'sms' => $recipient->phone ?: null,
            default => null,
        };
    }

    protected function composeContent(Model $recipient, string $channel, ?NotificationTemplate $template, array $payload): array
    {
        $variables = $this->buildVariables($recipient, Arr::get($payload, 'template_variables', []));

        if ($template) {
            if ($channel === 'email') {
                return [
                    'source' => 'template',
                    'subject' => $this->renderer->render($template->email_subject, $variables),
                    'body' => $this->renderer->render($template->email_body, $variables),
                ];
            }

            return [
                'source' => 'template',
                'subject' => null,
                'body' => $this->renderer->render($template->sms_body, $variables),
            ];
        }

        $title = $this->renderer->render((string) Arr::get($payload, 'title', ''), $variables);
        $message = $this->renderer->render((string) Arr::get($payload, 'message', ''), $variables);

        if ($channel === 'sms') {
            $message = trim(collect([$title, $message])->filter()->implode(PHP_EOL));
        }

        return [
            'source' => 'custom',
            'subject' => $title,
            'body' => $message,
        ];
    }

    protected function buildVariables(Model $recipient, array $templateVariables): array
    {
        $name = trim((string) ($recipient->name ?? ''));
        $email = trim((string) ($recipient->email ?? ''));
        $phone = trim((string) ($recipient->phone ?? ''));

        $recipientVariables = [
            'name' => $name,
            'user' => $name ?: class_basename($recipient),
            'email' => $email,
            'phone' => $phone,
            'user_name' => $name,
            'user_email' => $email,
        ];

        $templateVariables = collect($templateVariables)
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (string) $value])
            ->all();

        return array_filter(
            array_merge($recipientVariables, $templateVariables),
            static fn ($value): bool => $value !== ''
        );
    }

    protected function createLog(
        Model $recipient,
        string $channel,
        ?NotificationTemplate $template,
        string $address,
        array $content
    ): NotificationLog {
        return NotificationLog::query()->create([
            'template_slug' => $template?->slug,
            'channel' => $channel,
            'notifiable_type' => $recipient->getMorphClass(),
            'notifiable_id' => $recipient->getKey(),
            'status' => 'queued',
            'metadata' => [
                'source' => $content['source'],
                'template_name' => $template?->name,
                'recipient_name' => $recipient->name ?? null,
                'recipient_address' => $address,
                'subject' => $content['subject'],
                'body' => $content['body'],
            ],
        ]);
    }
}
