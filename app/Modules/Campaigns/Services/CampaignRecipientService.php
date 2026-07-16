<?php

namespace App\Modules\Campaigns\Services;

use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Media\Models\Media;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use Illuminate\Support\Str;

class CampaignRecipientService
{
    public function __construct(protected TemplateVariableMapper $variables) {}

    public function createForContact(Campaign $campaign, Contact $contact): CampaignRecipient
    {
        $identity = $this->resolveIdentity($campaign, $contact);
        $address = $this->resolveAddress($campaign, $contact, $identity);
        $skipReason = $this->sendabilityStatus($campaign, $contact, $identity, $address);

        $existing = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('contact_id', $contact->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return CampaignRecipient::query()->create([
            'workspace_id' => $campaign->workspace_id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_channel_id' => $identity?->id,
            'channel_account_id' => $campaign->channel_account_id,
            'provider' => $campaign->provider,
            'uuid' => (string) Str::uuid(),
            'to' => $contact->phone,
            'recipient_address' => $address,
            'status' => ($skipReason ?? CampaignRecipientStatus::Queued)->value,
            'payload' => [],
            'error_code' => $skipReason ? $this->skipErrorCode($campaign, $skipReason, $identity, $address) : null,
            'error_message' => $skipReason ? $this->skipErrorMessage($campaign, $skipReason, $identity, $address) : null,
            'queued_at' => $skipReason === null ? now() : null,
        ]);
    }

    public function buildPayload(Campaign $campaign, CampaignRecipient $recipient): array
    {
        if ($campaign->message_type === 'automation') {
            return [
                'type' => 'automation',
                'automation_id' => $campaign->automation_id,
            ];
        }

        $contact = $recipient->contact;

        return match ($campaign->provider) {
            'whatsapp' => $this->whatsappPayload($campaign, $contact),
            'telegram' => $this->telegramPayload($campaign, $contact, $recipient),
            'email' => $this->emailPayload($campaign, $contact),
            'sms' => $this->smsPayload($campaign, $contact),
            default => [],
        };
    }

    public function resolveIdentity(Campaign $campaign, Contact $contact): ?ContactProviderIdentity
    {
        if ($campaign->provider === 'telegram' || $campaign->provider === 'messenger' || $campaign->provider === 'instagram') {
            return ContactProviderIdentity::query()
                ->where('workspace_id', $campaign->workspace_id)
                ->where('contact_id', $contact->id)
                ->where('provider', $campaign->provider)
                ->first();
        }

        return null;
    }

    public function resolveAddress(Campaign $campaign, Contact $contact, ?ContactProviderIdentity $identity): ?string
    {
        return match ($campaign->provider) {
            'whatsapp', 'sms' => $contact->phone,
            'email' => $contact->email,
            'telegram', 'messenger', 'instagram' => $identity?->address ?: $identity?->provider_contact_id,
            default => null,
        };
    }

    public function sendabilityStatus(Campaign $campaign, Contact $contact, ?ContactProviderIdentity $identity, ?string $address): ?CampaignRecipientStatus
    {
        if ($contact->blocked_at !== null) {
            return CampaignRecipientStatus::SkippedBlocked;
        }

        if ($contact->isOptedOut()) {
            return CampaignRecipientStatus::SkippedOptOut;
        }

        if (in_array($campaign->provider, ['whatsapp', 'sms'], true)) {
            if (! $this->isValidE164($address)) {
                return CampaignRecipientStatus::SkippedInvalid;
            }
        }

        if ($campaign->provider === 'email' && ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return CampaignRecipientStatus::SkippedInvalid;
        }

        if ($campaign->provider === 'telegram' && blank($address)) {
            return CampaignRecipientStatus::SkippedPolicy;
        }

        if (in_array($campaign->provider, ['messenger', 'instagram'], true) && blank($address)) {
            return CampaignRecipientStatus::SkippedInvalid;
        }

        return null;
    }

    protected function skipErrorCode(Campaign $campaign, CampaignRecipientStatus $status, ?ContactProviderIdentity $identity, ?string $address): ?string
    {
        if ($campaign->provider === 'telegram' && blank($address)) {
            return 'telegram_opt_in_missing';
        }

        return match ($status) {
            CampaignRecipientStatus::SkippedOptOut => 'contact_opted_out',
            CampaignRecipientStatus::SkippedBlocked => 'contact_blocked',
            CampaignRecipientStatus::SkippedInvalidPhone => 'invalid_phone',
            CampaignRecipientStatus::SkippedInvalid => 'invalid_recipient',
            CampaignRecipientStatus::SkippedPolicy => 'provider_policy',
            default => null,
        };
    }

    protected function skipErrorMessage(Campaign $campaign, CampaignRecipientStatus $status, ?ContactProviderIdentity $identity, ?string $address): ?string
    {
        if ($campaign->provider === 'telegram' && blank($address)) {
            return 'Telegram opt-in missing. Ask the contact to start the connected Telegram bot first.';
        }

        return match ($status) {
            CampaignRecipientStatus::SkippedOptOut => 'Contact is opted out.',
            CampaignRecipientStatus::SkippedBlocked => 'Contact is blocked.',
            CampaignRecipientStatus::SkippedInvalidPhone => 'Contact phone number is invalid.',
            CampaignRecipientStatus::SkippedInvalid => 'Recipient address is invalid.',
            CampaignRecipientStatus::SkippedPolicy => 'Recipient cannot be sent because of provider policy.',
            default => null,
        };
    }

    public function transition(CampaignRecipient $recipient, CampaignRecipientStatus|string $status): void
    {
        $status = $status instanceof CampaignRecipientStatus ? $status : CampaignRecipientStatus::tryFrom($status) ?? CampaignRecipientStatus::Failed;

        $update = ['status' => $status->value];

        match ($status) {
            CampaignRecipientStatus::Sending => $update['sending_at'] = now(),
            CampaignRecipientStatus::Sent => $update['sent_at'] = now(),
            CampaignRecipientStatus::Delivered => $update['delivered_at'] = now(),
            CampaignRecipientStatus::Opened => $update['opened_at'] = now(),
            CampaignRecipientStatus::Read => $update['read_at'] = now(),
            CampaignRecipientStatus::Clicked => $update['clicked_at'] = now(),
            CampaignRecipientStatus::Replied => $update['replied_at'] = now(),
            CampaignRecipientStatus::Failed => $update['failed_at'] = now(),
            default => null,
        };

        $recipient->update($update);
    }

    public function markFailed(CampaignRecipient $recipient, ?string $errorCode = null, ?string $errorMessage = null): void
    {
        $recipient->update([
            'status' => CampaignRecipientStatus::Failed->value,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    protected function whatsappPayload(Campaign $campaign, Contact $contact): array
    {
        if ($campaign->message_type === 'custom') {
            $body = $this->variables->map((string) $campaign->message_body, $contact, $campaign->variables ?? []);

            return [
                'type' => 'text',
                'body' => $body,
                'meta_payload' => [
                    'messaging_product' => 'whatsapp',
                    'to' => preg_replace('/\D+/', '', (string) $contact->phone),
                    'type' => 'text',
                    'text' => ['body' => $body],
                ],
            ];
        }

        $template = $campaign->messageTemplate;
        $components = $template?->components ?? [];
        $runtimeComponents = $this->approvedRuntimeComponents($campaign, $template);
        $variables = $campaign->variables ?? [];

        $mappedComponents = $this->mapTemplateComponents($runtimeComponents, $components, $contact, $variables);
        $body = $this->renderWhatsAppTemplatePreview($components, $contact, $variables);

        $metaTemplate = [
            'name' => $template?->name,
            'language' => ['code' => $template?->language ?? 'en_US'],
        ];

        if ($mappedComponents !== []) {
            $metaTemplate['components'] = $mappedComponents;
        }

        return [
            'type' => 'template',
            'template_name' => $template?->name,
            'language' => $template?->language ?? 'en_US',
            'body' => $body,
            'components' => $mappedComponents,
            'meta_payload' => [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D+/', '', (string) $contact->phone),
                'type' => 'template',
                'template' => $metaTemplate,
            ],
        ];
    }

    protected function renderWhatsAppTemplatePreview(array $components, Contact $contact, array $variables): ?string
    {
        $text = collect($components)
            ->filter(fn (array $component): bool => in_array(strtoupper((string) ($component['type'] ?? '')), ['HEADER', 'BODY', 'FOOTER'], true))
            ->map(fn (array $component): string => (string) ($component['text'] ?? ''))
            ->filter()
            ->map(fn (string $text): string => $this->variables->map($text, $contact, $variables))
            ->implode("\n\n");

        return filled($text) ? $text : null;
    }

    protected function telegramPayload(Campaign $campaign, Contact $contact, CampaignRecipient $recipient): array
    {
        $variables = $campaign->variables ?? [];
        $buttons = $campaign->settings['buttons'] ?? [];

        if ($campaign->message_type === 'template') {
            $template = $campaign->messageTemplate;
            $components = collect($template?->components ?? []);
            $body = $this->variables->map(
                (string) data_get($components->firstWhere('type', 'BODY'), 'text', ''),
                $contact,
                $variables
            );
            $buttons = collect(data_get($components->firstWhere('type', 'BUTTONS'), 'buttons', []))
                ->map(fn (array $button): array => array_filter([
                    'text' => $button['text'] ?? '',
                    'url' => isset($button['url']) ? $this->variables->map((string) $button['url'], $contact, $variables) : null,
                    'callback_data' => isset($button['callback_data']) ? $this->variables->map((string) $button['callback_data'], $contact, $variables) : null,
                ]))
                ->values()
                ->all();
        } else {
            $body = $this->variables->map((string) $campaign->message_body, $contact, $variables);
        }

        return [
            'type' => 'text',
            'body' => $body,
            'parse_mode' => 'HTML',
            'buttons' => $buttons,
            'chat_id' => $recipient->recipient_address,
        ];
    }

    protected function emailPayload(Campaign $campaign, Contact $contact): array
    {
        $variables = $campaign->variables ?? [];
        $subject = $this->variables->map((string) $campaign->message_subject, $contact, $variables);
        $htmlBody = $this->variables->map((string) $campaign->message_body, $contact, $variables);

        return [
            'type' => 'email',
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => strip_tags($htmlBody),
            'append_unsubscribe' => ! ($campaign->settings['disable_unsubscribe'] ?? false),
        ];
    }

    protected function smsPayload(Campaign $campaign, Contact $contact): array
    {
        $body = $this->variables->map((string) $campaign->message_body, $contact, $campaign->variables ?? []);

        return [
            'type' => 'text',
            'body' => $body,
        ];
    }

    protected function approvedRuntimeComponents(Campaign $campaign, ?MessageTemplate $template): array
    {
        if (! $template) {
            return [];
        }

        $channel = $campaign->relationLoaded('channelAccount')
            ? $campaign->channelAccount
            : ChannelAccount::query()->find($campaign->channel_account_id);

        $submission = $template->submissions()
            ->where('status', MessageTemplateStatus::Approved->value)
            ->when(
                filled($channel?->provider_account_id),
                fn ($query) => $query->where('provider_account_id', (string) $channel->provider_account_id)
            )
            ->latest('synced_at')
            ->latest('submitted_at')
            ->first();

        $components = data_get($submission?->submission_payload, 'components')
            ?: data_get($template->submission_payload, 'components')
            ?: $template->components
            ?: [];

        return is_array($components) ? $components : [];
    }

    protected function mapTemplateComponents(array $runtimeComponents, array $valueComponents, Contact $contact, array $variables): array
    {
        return collect($runtimeComponents)
            ->flatMap(fn (array $component): array => $this->runtimeTemplateComponents($component, $valueComponents, $contact, $variables))
            ->values()
            ->all();
    }

    protected function runtimeTemplateComponents(array $component, array $valueComponents, Contact $contact, array $variables): array
    {
        $type = strtoupper((string) ($component['type'] ?? ''));
        $valueComponent = $this->matchingValueComponent($component, $valueComponents);

        if ($type === 'HEADER') {
            return $this->headerRuntimeComponent($component, $valueComponent, $contact, $variables);
        }

        if ($type === 'BODY') {
            return $this->bodyRuntimeComponent($component, $valueComponent, $contact, $variables);
        }

        if ($type === 'BUTTONS') {
            return $this->buttonRuntimeComponents($component, $valueComponent, $contact, $variables);
        }

        return [];
    }

    protected function matchingValueComponent(array $runtimeComponent, array $valueComponents): array
    {
        $type = strtoupper((string) ($runtimeComponent['type'] ?? ''));

        return collect($valueComponents)
            ->first(fn (array $component): bool => strtoupper((string) ($component['type'] ?? '')) === $type) ?? [];
    }

    protected function headerRuntimeComponent(array $component, array $valueComponent, Contact $contact, array $variables): array
    {
        $format = strtoupper((string) ($component['format'] ?? 'TEXT'));

        if ($format === 'TEXT') {
            $parameters = $this->schemaTextParameters(
                (string) ($component['text'] ?? ''),
                (string) ($valueComponent['text'] ?? $component['text'] ?? ''),
                $contact,
                $variables,
                (array) data_get($valueComponent, 'example.header_text', data_get($component, 'example.header_text', []))
            );

            return $parameters === [] ? [] : [[
                'type' => 'header',
                'parameters' => $parameters,
            ]];
        }

        if (! in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            return [];
        }

        $url = data_get($valueComponent, 'media_url') ?: data_get($component, 'media_url');

        if (blank($url) && filled($valueComponent['media_id'] ?? null)) {
            $media = Media::query()->find((int) $valueComponent['media_id']);
            $url = $media?->url;
        }

        if (blank($url) && filled($component['media_id'] ?? null)) {
            $media = Media::query()->find((int) $component['media_id']);
            $url = $media?->url;
        }

        if (blank($url)) {
            return [];
        }

        $parameterType = strtolower($format);
        $media = ['link' => (string) $url];

        $mediaName = $valueComponent['media_name'] ?? $component['media_name'] ?? null;

        if ($parameterType === 'document' && filled($mediaName)) {
            $media['filename'] = (string) $mediaName;
        }

        return [[
            'type' => 'header',
            'parameters' => [[
                'type' => $parameterType,
                $parameterType => $media,
            ]],
        ]];
    }

    protected function bodyRuntimeComponent(array $component, array $valueComponent, Contact $contact, array $variables): array
    {
        $parameters = $this->schemaTextParameters(
            (string) ($component['text'] ?? ''),
            (string) ($valueComponent['text'] ?? $component['text'] ?? ''),
            $contact,
            $variables,
            (array) data_get($valueComponent, 'example.body_text.0', data_get($component, 'example.body_text.0', []))
        );

        return $parameters === [] ? [] : [[
            'type' => 'body',
            'parameters' => $parameters,
        ]];
    }

    protected function buttonRuntimeComponents(array $component, array $valueComponent, Contact $contact, array $variables): array
    {
        return collect($component['buttons'] ?? [])
            ->map(function (array $button, int $index) use ($valueComponent, $contact, $variables): ?array {
                if (strtoupper((string) ($button['type'] ?? '')) !== 'URL') {
                    return null;
                }

                $valueButton = (array) data_get($valueComponent, "buttons.{$index}", []);
                $parameters = $this->schemaTextParameters(
                    (string) ($button['url'] ?? ''),
                    (string) ($valueButton['url'] ?? $button['url'] ?? ''),
                    $contact,
                    $variables,
                    (array) ($valueButton['example'] ?? $button['example'] ?? [])
                );

                if ($parameters === []) {
                    return null;
                }

                return [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => (string) $index,
                    'parameters' => $parameters,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function schemaTextParameters(string $schemaText, string $valueText, Contact $contact, array $variables, array $examples = []): array
    {
        $schemaVariables = $this->templateVariables($schemaText);

        if ($schemaVariables === []) {
            return [];
        }

        $valueVariables = $this->templateVariables($valueText);

        return collect($schemaVariables)
            ->map(function (string $schemaKey, int $index) use ($valueVariables, $contact, $variables, $examples): array {
                $valueKey = $valueVariables[$index] ?? $schemaKey;

                return [
                    'type' => 'text',
                    'text' => $this->templateParameterText($valueKey, $index, $contact, $variables, $examples),
                ];
            })
            ->values()
            ->all();
    }

    protected function textParameters(string $text, Contact $contact, array $variables, array $examples = []): array
    {
        return collect($this->templateVariables($text))
            ->map(fn (string $key, int $index): array => [
                'type' => 'text',
                'text' => $this->templateParameterText($key, $index, $contact, $variables, $examples),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function templateVariables(string $text): array
    {
        preg_match_all('/\{\{\s*([^}]+)\s*\}\}/', $text, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function templateParameterText(string $key, int $index, Contact $contact, array $variables, array $examples): string
    {
        $mapped = $this->variables->map('{{'.$key.'}}', $contact, $variables);

        if (filled($mapped)) {
            return $mapped;
        }

        $example = $examples[$index] ?? $examples[$key] ?? null;

        if (filled($example)) {
            return (string) $example;
        }

        return '';
    }

    protected function isValidE164(?string $phone): bool
    {
        return is_string($phone) && preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1;
    }
}
