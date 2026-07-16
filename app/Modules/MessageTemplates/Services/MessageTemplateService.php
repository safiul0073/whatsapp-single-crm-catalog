<?php

namespace App\Modules\MessageTemplates\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\MediaService;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MessageTemplates\Models\MessageTemplateSubmission;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudClient;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageTemplateService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ChannelManager $channels,
        protected WhatsAppCloudClient $client,
        protected WhatsAppSettingsService $settings,
        protected MediaService $media,
        protected MessageTemplateTokenService $tokens,
    ) {}

    public function listForUser(?User $user, ?string $provider = null): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);
        $provider = $this->normalizeProvider($provider);

        return MessageTemplate::query()
            ->with('submissions')
            ->where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->latest()
            ->paginate(20);
    }

    public function statsForUser(?User $user, ?string $provider = null): array
    {
        $workspace = $this->workspaces->current($user);
        $provider = $this->normalizeProvider($provider);
        $templates = MessageTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->get(['status']);

        return [
            'total' => $templates->count(),
            'approved' => $templates->where('status', MessageTemplateStatus::Approved)->count(),
            'pending' => $templates->filter(fn (MessageTemplate $template): bool => in_array($template->status, [
                MessageTemplateStatus::Submitted,
                MessageTemplateStatus::Pending,
                MessageTemplateStatus::InAppeal,
            ], true))->count(),
            'rejected' => $templates->filter(fn (MessageTemplate $template): bool => in_array($template->status, [
                MessageTemplateStatus::Rejected,
                MessageTemplateStatus::Failed,
                MessageTemplateStatus::Disabled,
            ], true))->count(),
        ];
    }

    public function channelsForUser(?User $user): Collection
    {
        $workspace = $this->workspaces->current($user);

        return ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'whatsapp')
            ->whereIn('status', [ChannelAccountStatus::Connected->value, ChannelAccountStatus::Draft->value])
            ->latest()
            ->get();
    }

    public function wabasForUser(?User $user): SupportCollection
    {
        return $this->wabaTokenSources($this->workspaces->current($user)->id);
    }

    public function store(?User $user, array $data, bool $submitToMeta = false): MessageTemplate
    {
        $workspace = $this->workspaces->current($user);
        $provider = $this->normalizeProvider($data['provider'] ?? null);
        $data = $this->storeHeaderMedia($data);
        $components = $provider === 'telegram'
            ? $this->telegramComponents($data)
            : $this->components($data);
        $compiled = $provider === 'telegram' ? null : $this->metaSubmissionPayload($data, $components);
        $payload = $compiled['payload'] ?? null;
        $variables = $this->variablesForComponents($components, $compiled['variables'] ?? []);

        $template = MessageTemplate::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'provider' => $provider, 'name' => $data['name'], 'language' => $data['language']],
            [
                'provider' => $provider,
                'category' => $data['category'] ?? ($provider === 'telegram' ? 'utility' : 'marketing'),
                'status' => $provider === 'telegram' ? MessageTemplateStatus::Approved->value : MessageTemplateStatus::Draft->value,
                'body' => $data['body'],
                'components' => $components,
                'buttons' => $data['buttons'] ?? [],
                'variables' => $variables,
                'submission_payload' => $payload,
            ]
        );

        if ($provider === 'whatsapp' && $submitToMeta) {
            $this->submit($user, $template, $data['provider_account_id'] ?? null);
        }

        return $template->fresh('submissions');
    }

    public function uniqueNameForUser(?User $user, string $provider, string $language, string $baseName): string
    {
        $workspace = $this->workspaces->current($user);
        $provider = $this->normalizeProvider($provider);
        $baseName = Str::of($baseName)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->toString();
        $baseName = $baseName !== '' ? Str::limit($baseName, 255, '') : 'ai_generated_template';
        $name = $baseName;
        $suffix = 2;

        while (MessageTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->where('language', $language)
            ->where('name', $name)
            ->exists()) {
            $suffixText = '_'.$suffix;
            $name = Str::limit($baseName, 255 - strlen($suffixText), '').$suffixText;
            $suffix++;
        }

        return $name;
    }

    public function update(?User $user, MessageTemplate $template, array $data, bool $submitToMeta = false): MessageTemplate
    {
        $template = $this->templateForUser($user, $template);
        $provider = $this->normalizeProvider($data['provider'] ?? $template->provider);
        $data = $this->storeHeaderMedia($data);
        $components = $provider === 'telegram'
            ? $this->telegramComponents($data)
            : $this->components($data);
        $compiled = $provider === 'telegram' ? null : $this->metaSubmissionPayload($data, $components);
        $payload = $compiled['payload'] ?? null;
        $variables = $this->variablesForComponents($components, $compiled['variables'] ?? []);

        $template->update([
            'provider' => $provider,
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => $data['category'] ?? ($provider === 'telegram' ? 'utility' : 'marketing'),
            'status' => $provider === 'telegram' ? MessageTemplateStatus::Approved->value : MessageTemplateStatus::Draft->value,
            'body' => $data['body'],
            'components' => $components,
            'buttons' => $data['buttons'] ?? [],
            'variables' => $variables,
            'submission_payload' => $payload,
        ]);

        if ($provider === 'whatsapp' && $submitToMeta) {
            $this->submit($user, $template, $data['provider_account_id'] ?? null);
        }

        return $template->fresh('submissions');
    }

    public function delete(?User $user, MessageTemplate $template): void
    {
        $template = $this->templateForUser($user, $template);
        $template->delete();
    }

    public function submit(?User $user, MessageTemplate $template, ?string $providerAccountId = null): array
    {
        $template = $this->templateForUser($user, $template);
        abort_unless($template->provider === 'whatsapp', 404);

        $channel = $this->wabaTokenSource($template->workspace_id, $providerAccountId);
        $compiled = $this->tokens->compilePayloadForMeta([
            'name' => $template->name,
            'language' => $template->language,
            'category' => strtoupper($template->category),
            'components' => $template->components ?? [],
        ]);

        if ($compiled['variables'] !== []) {
            $template->forceFill([
                'variables' => array_merge($template->variables ?? [], ['whatsapp' => $compiled['variables']]),
            ])->save();
        }

        $payload = $template->submission_payload ?: [
            'name' => $template->name,
            'language' => $template->language,
            'category' => strtoupper($template->category),
            'components' => $template->components ?? [],
        ];

        return $this->submitToMeta($template, $channel, $payload);
    }

    public function sync(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $channels = $this->wabaTokenSources($workspace->id);

        if ($channels->isEmpty()) {
            throw ValidationException::withMessages([
                'channel' => 'Connect a WhatsApp Business Account before syncing templates.',
            ]);
        }

        $synced = 0;
        $ok = true;
        $responses = [];

        foreach ($channels as $channel) {
            $result = $this->channels->syncTemplates($channel);
            $synced += (int) ($result['synced'] ?? 0);
            $ok = $ok && (bool) ($result['ok'] ?? false);
            $responses[] = $result['response'] ?? null;
        }

        return ['ok' => $ok, 'synced' => $synced, 'response' => $responses];
    }

    public function templateForUser(?User $user, MessageTemplate $template): MessageTemplate
    {
        $workspace = $this->workspaces->current($user);

        abort_unless($template->workspace_id === $workspace->id, 404);

        return $template;
    }

    public function bodyFromComponents(?array $components): string
    {
        return (string) data_get(collect($components ?? [])->firstWhere('type', 'BODY'), 'text', '');
    }

    public function headerTextFromComponents(?array $components): ?string
    {
        $header = collect($components ?? [])->firstWhere('type', 'HEADER');

        return data_get($header, 'format') === 'TEXT' ? data_get($header, 'text') : null;
    }

    public function footerTextFromComponents(?array $components): ?string
    {
        return data_get(collect($components ?? [])->firstWhere('type', 'FOOTER'), 'text');
    }

    protected function components(array $data): array
    {
        $components = [];
        $header = $data['header'] ?? [];
        $headerType = $header['type'] ?? 'none';

        if ($headerType === 'text' && filled($header['text'] ?? null)) {
            $component = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $header['text']];

            if ($this->tokens->hasTokens($header['text']) && filled($header['example'] ?? null)) {
                $component['example'] = ['header_text' => [$header['example']]];
            }

            $components[] = $component;
        }

        if (in_array($headerType, ['image', 'video', 'document'], true) && filled($header['media_id'] ?? null)) {
            $media = Media::query()->find($header['media_id']);
            $component = [
                'type' => 'HEADER',
                'format' => strtoupper($headerType),
                'media_id' => (int) $header['media_id'],
            ];

            if ($media) {
                $component['media_name'] = $media->original_name;
                $component['media_url'] = $media->url;
                $component['media_mime_type'] = $media->mime_type;
            }

            $components[] = $component;
        }

        if (in_array($headerType, ['image', 'video', 'document'], true)
            && blank($header['media_id'] ?? null)
            && filled($header['handle'] ?? null)) {
            $components[] = [
                'type' => 'HEADER',
                'format' => strtoupper($headerType),
                'example' => ['header_handle' => [$header['handle']]],
            ];
        }

        $body = ['type' => 'BODY', 'text' => $data['body']];
        $bodyExamples = $this->exampleValues((string) $data['body'], $data['body_examples'] ?? []);

        if ($bodyExamples !== []) {
            $body['example'] = ['body_text' => [$bodyExamples]];
        }

        $components[] = $body;

        if (filled(data_get($data, 'footer.text'))) {
            $components[] = ['type' => 'FOOTER', 'text' => data_get($data, 'footer.text')];
        }

        $buttons = $this->buttons($data['buttons'] ?? []);

        if ($buttons !== []) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $components;
    }

    protected function telegramComponents(array $data): array
    {
        $components = [
            ['type' => 'BODY', 'text' => $data['body']],
        ];

        $buttons = $this->telegramButtons($data['buttons'] ?? []);

        if ($buttons !== []) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $components;
    }

    protected function telegramButtons(array $buttons): array
    {
        return collect($buttons)
            ->filter(fn (array $button): bool => filled($button['type'] ?? null) && filled($button['text'] ?? null))
            ->take(10)
            ->map(function (array $button): array {
                if (($button['type'] ?? null) === 'url') {
                    return array_filter([
                        'type' => 'URL',
                        'text' => $button['text'],
                        'url' => $button['url'] ?? '',
                    ]);
                }

                return array_filter([
                    'type' => 'CALLBACK',
                    'text' => $button['text'],
                    'callback_data' => $button['callback_data'] ?? $button['text'],
                ]);
            })
            ->values()
            ->all();
    }

    protected function buttons(array $buttons): array
    {
        return collect($buttons)
            ->filter(fn (array $button): bool => filled($button['type'] ?? null) && filled($button['text'] ?? null))
            ->take(10)
            ->map(function (array $button): array {
                return match ($button['type']) {
                    'url' => array_filter([
                        'type' => 'URL',
                        'text' => $button['text'],
                        'url' => $button['url'] ?? '',
                        'example' => filled($button['example'] ?? null) ? [$button['example']] : null,
                    ]),
                    'phone_number' => [
                        'type' => 'PHONE_NUMBER',
                        'text' => $button['text'],
                        'phone_number' => $button['phone_number'] ?? '',
                    ],
                    default => [
                        'type' => 'QUICK_REPLY',
                        'text' => $button['text'],
                    ],
                };
            })
            ->values()
            ->all();
    }

    protected function storeHeaderMedia(array $data): array
    {
        if (! isset($data['header_media_file'])
            || ! in_array(data_get($data, 'header.type'), ['image', 'video', 'document'], true)) {
            unset($data['header_media_file']);

            return $data;
        }

        $media = $this->media->upload($data['header_media_file']);
        $data['header']['media_id'] = $media->id;
        unset($data['header_media_file']);

        return $data;
    }

    /**
     * @return array{payload: array<string, mixed>, variables: array<string, mixed>}
     */
    protected function metaSubmissionPayload(array $data, array $components): array
    {
        return $this->tokens->compilePayloadForMeta([
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => strtoupper($data['category']),
            'components' => $components,
        ]);
    }

    protected function variablesForComponents(array $components, array $whatsappVariables = []): array
    {
        $variables = [];

        foreach ($components as $component) {
            if (filled($component['text'] ?? null)) {
                $variables[strtolower((string) ($component['type'] ?? 'text'))] = $this->tokens->extract((string) $component['text']);
            }

            if (($component['type'] ?? null) === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $index => $button) {
                    $buttonText = (string) ($button['url'] ?? $button['callback_data'] ?? '');
                    $buttonVariables = $this->tokens->extract($buttonText);

                    if ($buttonVariables !== []) {
                        $variables['buttons'][$index] = $buttonVariables;
                    }
                }
            }
        }

        if ($whatsappVariables !== []) {
            $variables['whatsapp'] = $whatsappVariables;
        }

        return $variables;
    }

    protected function exampleValues(string $text, array $examples): array
    {
        $tokens = $this->tokens->extract($text);

        return collect($tokens)
            ->map(fn (string $token): mixed => $examples[$token] ?? null)
            ->filter(fn ($value): bool => filled($value))
            ->values()
            ->all();
    }

    protected function normalizeProvider(?string $provider): string
    {
        return in_array($provider, ['whatsapp', 'telegram'], true) ? $provider : 'whatsapp';
    }

    protected function wabaTokenSources(int $workspaceId): SupportCollection
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'whatsapp')
            ->whereIn('status', [ChannelAccountStatus::Connected->value, ChannelAccountStatus::Draft->value])
            ->whereNotNull('provider_account_id')
            ->latest()
            ->get()
            ->filter(fn (ChannelAccount $channel): bool => filled($channel->provider_account_id))
            ->groupBy('provider_account_id')
            ->map(fn ($channels): ChannelAccount => $channels->first())
            ->values();
    }

    protected function wabaTokenSource(int $workspaceId, ?string $providerAccountId = null): ChannelAccount
    {
        $channels = $this->wabaTokenSources($workspaceId);

        if ($channels->isEmpty()) {
            throw ValidationException::withMessages([
                'provider_account_id' => 'Connect a WhatsApp Business Account before submitting templates.',
            ]);
        }

        if (filled($providerAccountId)) {
            $channel = $channels->firstWhere('provider_account_id', $providerAccountId);

            if ($channel) {
                return $channel;
            }

            throw ValidationException::withMessages([
                'provider_account_id' => 'Choose a WhatsApp Business Account connected to this workspace.',
            ]);
        }

        if ($channels->count() > 1) {
            throw ValidationException::withMessages([
                'provider_account_id' => 'Choose which WhatsApp Business Account should receive this template.',
            ]);
        }

        return $channels->first();
    }

    protected function submitToMeta(MessageTemplate $template, ChannelAccount $channel, array $payload): array
    {
        $payload = $this->payloadForMeta($payload, $channel);
        $this->validateMetaPayload($payload);

        $response = $this->client->submitTemplate(
            (string) $channel->provider_account_id,
            (string) $channel->credential('access_token'),
            $payload
        );
        $json = $response->json() ?? [];

        MessageTemplateSubmission::query()->updateOrCreate(
            [
                'workspace_id' => $template->workspace_id,
                'message_template_id' => $template->id,
                'provider_account_id' => (string) $channel->provider_account_id,
            ],
            [
                'channel_account_id' => $channel->id,
                'provider' => 'whatsapp',
                'whatsapp_template_id' => Arr::get($json, 'id'),
                'status' => $response->successful() ? MessageTemplateStatus::Submitted->value : MessageTemplateStatus::Failed->value,
                'submission_payload' => $payload,
                'meta_response' => $json,
                'submitted_at' => now(),
            ]
        );

        $template->update([
            'status' => $response->successful() ? MessageTemplateStatus::Submitted->value : MessageTemplateStatus::Failed->value,
            'submission_payload' => $payload,
        ]);
        $this->refreshTemplateSummaryStatus($template);

        return ['ok' => $response->successful(), 'response' => $json];
    }

    public function metaErrorMessage(array $response): ?string
    {
        return data_get($response, 'error.error_user_msg')
            ?: data_get($response, 'error.error_data.details')
            ?: data_get($response, 'error.message');
    }

    protected function validateMetaPayload(array $payload): void
    {
        foreach ($payload['components'] ?? [] as $component) {
            if (($component['type'] ?? null) !== 'BODY') {
                continue;
            }

            $body = (string) ($component['text'] ?? '');

            if ($this->tokens->hasLeadingOrTrailingToken($body)) {
                throw ValidationException::withMessages([
                    'body' => 'Variables cannot be at the start or end of the template body. Add text before the first variable and after the last variable.',
                ]);
            }
        }
    }

    protected function payloadForMeta(array $payload, ChannelAccount $channel): array
    {
        $payload['components'] = collect($payload['components'] ?? [])
            ->map(function (array $component) use ($channel): array {
                if (($component['type'] ?? null) === 'HEADER'
                    && in_array($component['format'] ?? null, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                    $handle = data_get($component, 'example.header_handle.0')
                        ?: $this->templateMediaHandle((int) ($component['media_id'] ?? 0), $channel);

                    $component['example'] = ['header_handle' => [$handle]];
                }

                unset($component['media_id'], $component['media_name'], $component['media_url'], $component['media_mime_type']);

                return $component;
            })
            ->values()
            ->all();

        return $payload;
    }

    protected function templateMediaHandle(int $mediaId, ChannelAccount $channel): string
    {
        $media = Media::query()->find($mediaId);

        if (! $media) {
            throw ValidationException::withMessages([
                'header_media_file' => 'Upload a valid media example before submitting this template.',
            ]);
        }

        $appId = trim((string) $this->settings->get('whatsapp_meta_app_id'));

        if ($appId === '') {
            throw ValidationException::withMessages([
                'header_media_file' => 'Configure the WhatsApp Meta App ID before submitting media header templates.',
            ]);
        }

        $response = $this->client->uploadTemplateMedia(
            $appId,
            (string) $channel->credential('access_token'),
            Storage::disk($media->disk)->path($media->path),
            $media->original_name,
            $media->mime_type,
            $media->size,
        );

        if (! $response->successful() || blank($response->json('h'))) {
            throw ValidationException::withMessages([
                'header_media_file' => 'Meta rejected the media header example upload.',
            ]);
        }

        return (string) $response->json('h');
    }

    public function refreshTemplateSummaryStatus(MessageTemplate $template): void
    {
        $statuses = $template->submissions()
            ->pluck('status')
            ->map(fn ($status) => $status instanceof MessageTemplateStatus ? $status->value : (string) $status)
            ->all();

        $summary = match (true) {
            in_array(MessageTemplateStatus::Approved->value, $statuses, true) => MessageTemplateStatus::Approved,
            count(array_intersect($statuses, [
                MessageTemplateStatus::Submitted->value,
                MessageTemplateStatus::Pending->value,
                MessageTemplateStatus::InAppeal->value,
            ])) > 0 => MessageTemplateStatus::Pending,
            count(array_intersect($statuses, [
                MessageTemplateStatus::Rejected->value,
                MessageTemplateStatus::Disabled->value,
                MessageTemplateStatus::Paused->value,
                MessageTemplateStatus::PendingDeletion->value,
            ])) > 0 => MessageTemplateStatus::Rejected,
            in_array(MessageTemplateStatus::Failed->value, $statuses, true) => MessageTemplateStatus::Failed,
            default => MessageTemplateStatus::Draft,
        };

        $template->forceFill(['status' => $summary->value])->save();
    }
}
