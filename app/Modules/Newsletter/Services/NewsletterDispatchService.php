<?php

namespace App\Modules\Newsletter\Services;

use App\Modules\Newsletter\Models\Subscriber;
use App\Modules\NotificationTemplates\Jobs\SendChannelNotificationJob;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Services\TemplateRenderer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class NewsletterDispatchService
{
    public function __construct(
        protected TemplateRenderer $renderer
    ) {}

    public function dispatch(array $payload): int
    {
        $this->ensureEmailEnabled();

        $template = $this->resolveTemplate(Arr::get($payload, 'template_id'));
        $subscribers = $this->resolveSubscribers($payload);

        $queuedCount = 0;

        foreach ($subscribers as $subscriber) {
            if (! $subscriber->email) {
                continue;
            }

            $content = $this->composeContent($subscriber, $template, $payload);
            $log = $this->createLog($subscriber, $template, $content);

            SendChannelNotificationJob::dispatch(
                notificationLogId: $log->id,
                channel: 'email',
                recipient: $subscriber->email,
                subject: $content['subject'],
                body: $content['body'],
                source: $content['source'],
            );

            $queuedCount++;
        }

        if ($queuedCount === 0) {
            throw ValidationException::withMessages([
                'recipient_type' => __('No newsletter subscribers are available for the selected recipient group.'),
            ]);
        }

        return $queuedCount;
    }

    protected function ensureEmailEnabled(): void
    {
        if (! (bool) setting('enable_email_notifications', true)) {
            throw ValidationException::withMessages([
                'template_id' => __('Email notifications are currently disabled in notification settings.'),
            ]);
        }
    }

    protected function resolveTemplate(mixed $templateId): ?NotificationTemplate
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
     * @return Collection<int, Subscriber>
     */
    protected function resolveSubscribers(array $payload): Collection
    {
        $query = Subscriber::query()->orderBy('email');

        return match ($payload['recipient_type']) {
            'active' => $query->active()->get(),
            'single' => $query->whereKey((int) Arr::get($payload, 'subscriber_id'))->get(),
            default => $query->get(),
        };
    }

    /**
     * @return array{source: string, subject: string, body: string}
     */
    protected function composeContent(Subscriber $subscriber, ?NotificationTemplate $template, array $payload): array
    {
        $variables = $this->buildVariables($subscriber, Arr::get($payload, 'template_variables', []));

        if ($template) {
            return [
                'source' => 'template',
                'subject' => $this->renderer->render($template->email_subject, $variables),
                'body' => $this->renderer->render($template->email_body, $variables),
            ];
        }

        return [
            'source' => 'custom_html',
            'subject' => $this->renderer->render((string) Arr::get($payload, 'title', ''), $variables),
            'body' => $this->renderer->render((string) Arr::get($payload, 'message', ''), $variables),
        ];
    }

    /**
     * @param  array<string, mixed>  $templateVariables
     * @return array<string, string>
     */
    protected function buildVariables(Subscriber $subscriber, array $templateVariables): array
    {
        $email = trim((string) $subscriber->email);
        $name = str($email)->before('@')->replace(['.', '_', '-'], ' ')->title()->toString();

        $recipientVariables = [
            'name' => $name,
            'user' => $name,
            'email' => $email,
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

    /**
     * @param  array{source: string, subject: string, body: string}  $content
     */
    protected function createLog(Subscriber $subscriber, ?NotificationTemplate $template, array $content): NotificationLog
    {
        return NotificationLog::query()->create([
            'template_slug' => $template?->slug,
            'channel' => 'email',
            'notifiable_type' => $subscriber->getMorphClass(),
            'notifiable_id' => $subscriber->getKey(),
            'status' => 'queued',
            'metadata' => [
                'source' => $content['source'],
                'template_name' => $template?->name,
                'recipient_name' => str($subscriber->email)->before('@')->replace(['.', '_', '-'], ' ')->title()->toString(),
                'recipient_address' => $subscriber->email,
                'subject' => $content['subject'],
                'body' => $content['body'],
            ],
        ]);
    }
}
