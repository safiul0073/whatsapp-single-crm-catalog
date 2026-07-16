<?php

namespace App\Modules\WhatsAppCloud\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MetaSocial\Services\MetaSocialClient;
use App\Modules\Telegram\Services\TelegramBotProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChannelSetupService
{
    public const HIDDEN_USER_PROVIDERS = ['messenger', 'instagram', 'threads'];

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ChannelManager $channels,
        protected WhatsAppCloudClient $client,
        protected WhatsAppSettingsService $settings,
        protected MetaSocialClient $metaSocial,
    ) {}

    public function pageData(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $providers = collect($this->providerCatalog())
            ->except(self::HIDDEN_USER_PROVIDERS)
            ->all();
        $allChannels = $this->workspaceChannels($workspace->id)
            ->reject(fn (ChannelAccount $channel): bool => in_array($channel->provider, self::HIDDEN_USER_PROVIDERS, true))
            ->values();
        $channelsByProvider = $allChannels->keyBy('provider');
        $channels = $allChannels->where('provider', 'whatsapp')->values();
        $channel = $channelsByProvider->get('whatsapp');

        return [
            'workspace' => $workspace,
            'channel' => $channel,
            'channels' => $channels,
            'allChannels' => $allChannels,
            'channelsByProvider' => $channelsByProvider,
            'channelProviders' => $providers,
            'testableProviders' => collect($this->channels->providers())
                ->except(self::HIDDEN_USER_PROVIDERS)
                ->all(),
            'approvedTemplatesCount' => MessageTemplate::query()
                ->where('workspace_id', $workspace->id)
                ->where('provider', 'whatsapp')
                ->where('status', MessageTemplateStatus::Approved->value)
                ->count(),
            'webhookUrl' => $this->webhookUrlForProvider('whatsapp'),
            'webhookUrls' => collect(array_keys($providers))->mapWithKeys(fn (string $provider): array => [$provider => $this->webhookUrlForProvider($provider)])->all(),
            'embeddedSignup' => $this->embeddedSignupConfig(),
        ];
    }

    public function embeddedSignupConfig(): array
    {
        return [
            'enabled' => $this->settings->embeddedSignupReady(),
            'app_id' => $this->settings->get('whatsapp_meta_app_id', ''),
            'config_id' => $this->settings->get('whatsapp_embedded_signup_config_id', ''),
            'graph_api_version' => $this->settings->graphApiVersion(),
        ];
    }

    public function connect(?User $user, array $data): ChannelAccount
    {
        $workspace = $this->workspaces->current($user);
        $phonePayload = null;
        $lastError = null;

        try {
            $phonePayload = $this->validateManualCredentials($workspace->id, $data);
        } catch (ValidationException $exception) {
            $lastError = collect($exception->errors())->flatten()->first() ?: __('Meta credential validation failed.');
        }

        $name = $this->firstFilled($data['name'], $phonePayload['verified_name'] ?? null, 'WhatsApp Business');

        $existing = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'whatsapp')
            ->first();

        if ($existing &&
            (($existing->provider_account_id !== $data['waba_id']) ||
             ($existing->provider_phone_id !== $data['phone_number_id']))) {
            MessageTemplate::query()
                ->where('workspace_id', $workspace->id)
                ->where('provider', 'whatsapp')
                ->delete();
        }

        return ChannelAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'provider' => 'whatsapp',
            ],
            [
                'name' => $name,
                'status' => $lastError ? ChannelAccountStatus::Error->value : ChannelAccountStatus::Connected->value,
                'credentials' => [
                    'access_token' => $data['access_token'],
                    'source' => 'manual',
                ],
                'webhook_verify_token' => $data['webhook_verify_token'],
                'provider_account_id' => $data['waba_id'],
                'provider_phone_id' => $data['phone_number_id'],
                'provider_display_id' => $phonePayload['display_phone_number'] ?? $phonePayload['verified_name'] ?? null,
                'settings' => array_filter([
                    'inbox_active' => true,
                    'business_id' => $data['business_id'],
                    'verified_name' => $phonePayload['verified_name'] ?? null,
                    'last_error' => $lastError,
                ], fn ($value) => $value !== null && $value !== ''),
                'connected_at' => $lastError ? null : now(),
                'last_synced_at' => now(),
            ]
        );
    }

    public function connectGeneric(?User $user, array $data): ChannelAccount
    {
        $provider = (string) $data['provider'];
        $providers = $this->providerCatalog();

        abort_unless(isset($providers[$provider]) && $provider !== 'whatsapp', 404);

        $workspace = $this->workspaces->current($user);
        $webhookRequired = (bool) ($providers[$provider]['webhook_required'] ?? false);
        $verifyToken = $webhookRequired
            ? $this->firstFilled($data['webhook_verify_token'] ?? null, Str::random(40))
            : null;
        $existing = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->first();

        $credentials = $this->genericCredentials($providers[$provider], $data, $existing);
        $token = $this->firstFilled($credentials['access_token'] ?? null, $existing?->credential('access_token'));

        $validation = in_array($provider, ['messenger', 'instagram'], true)
            ? $this->validateMetaSocialCredentials($provider, (string) $data['provider_account_id'], $token)
            : ['ok' => true, 'payload' => null, 'error' => null];
        $displayId = $this->firstFilled(
            $data['provider_display_id'] ?? null,
            data_get($validation, $provider === 'instagram' ? 'payload.username' : 'payload.name')
        );
        $providerAccountId = $this->firstFilled(
            $data['provider_account_id'] ?? null,
            $credentials[$providers[$provider]['credential_provider_field'] ?? ''] ?? null,
            $existing?->provider_account_id,
            $provider
        );
        $connectionTest = ['ok' => true, 'error' => null];

        if ($validation['ok'] ?? false) {
            $connectionTest = $this->testGenericConnection(
                $provider,
                $workspace->id,
                $data['name'],
                $credentials,
                $providerAccountId,
                $displayId,
                $verifyToken,
            );
        }
        $status = ($validation['ok'] ?? false) && ($connectionTest['ok'] ?? false)
            ? ChannelAccountStatus::Connected
            : ChannelAccountStatus::Error;
        $lastError = $validation['error'] ?? ($connectionTest['error'] ?? null);

        $channel = ChannelAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'provider' => $provider,
            ],
            [
                'name' => $data['name'],
                'status' => $status->value,
                'credentials' => $credentials ?: null,
                'webhook_verify_token' => $verifyToken,
                'provider_account_id' => $providerAccountId,
                'provider_phone_id' => null,
                'provider_display_id' => $displayId,
                'settings' => array_filter([
                    'inbox_active' => true,
                    'manual_setup' => true,
                    'capabilities' => $providers[$provider]['capabilities'] ?? [],
                    'credential_provider' => $credentials[$providers[$provider]['credential_provider_field'] ?? ''] ?? null,
                    'last_error' => $lastError,
                    'last_connection_tested_at' => now()->toISOString(),
                    'telegram_bot_username' => $connectionTest['bot'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
                'connected_at' => $status === ChannelAccountStatus::Connected ? now() : null,
                'last_synced_at' => now(),
            ]
        );

        if ($provider === 'telegram' && $status === ChannelAccountStatus::Connected) {
            $webhookResult = app(TelegramBotProvider::class)->setWebhook($channel);

            $settings = $channel->settings ?? [];
            $settings['last_error'] = ($webhookResult['ok'] ?? false)
                ? null
                : ($webhookResult['message'] ?? 'Telegram webhook update failed.');

            if ($webhookResult['ok'] ?? false) {
                $settings['last_webhook_set_at'] = now()->toISOString();
            }

            $channel->update([
                'settings' => array_filter($settings, fn ($value) => $value !== null && $value !== ''),
            ]);
        }

        return $channel->fresh();
    }

    protected function testGenericConnection(
        string $provider,
        int $workspaceId,
        string $name,
        array $credentials,
        ?string $providerAccountId,
        ?string $displayId,
        ?string $verifyToken,
    ): array {
        if (! in_array($provider, $this->channels->providers(), true)) {
            return ['ok' => true, 'error' => null];
        }

        $probe = new ChannelAccount([
            'workspace_id' => $workspaceId,
            'provider' => $provider,
            'name' => $name,
            'status' => ChannelAccountStatus::Draft->value,
            'credentials' => $credentials,
            'provider_account_id' => $providerAccountId,
            'provider_display_id' => $displayId,
            'webhook_verify_token' => $verifyToken,
        ]);

        try {
            return $this->channels->testConnection($probe);
        } catch (\Throwable $exception) {
            return ['ok' => false, 'provider' => $provider, 'error' => $exception->getMessage()];
        }
    }

    protected function genericCredentials(array $providerConfig, array $data, ?ChannelAccount $existing): array
    {
        $credentials = $existing?->credentials ?? [];
        $selector = $providerConfig['credential_provider_field'] ?? null;
        $sameCredentialProvider = ! $selector
            || (($existing?->credentials[$selector] ?? null) === ($data[$selector] ?? null));

        foreach (($providerConfig['fields'] ?? []) as $field) {
            $name = $field['name'] ?? null;

            if (! $name || in_array($name, ['name', 'provider_account_id', 'provider_display_id'], true)) {
                continue;
            }

            if (! $this->fieldConditionApplies($field, $data)) {
                unset($credentials[$name]);

                continue;
            }

            $value = $data[$name] ?? null;

            if (($field['secret'] ?? false) && blank($value) && $sameCredentialProvider) {
                $value = $existing?->credentials[$name] ?? null;
            }

            if (($value === null || $value === '') && array_key_exists('default', $field)) {
                $value = $field['default'];
            }

            if ($value === null || $value === '') {
                unset($credentials[$name]);

                continue;
            }

            $credentials[$name] = $value;
        }

        if (filled($credentials['access_token'] ?? null)) {
            $credentials['source'] = $credentials['source'] ?? 'manual';
        }

        return $credentials;
    }

    protected function fieldConditionApplies(array $field, array $data): bool
    {
        $showWhen = $field['show_when'] ?? null;

        if (! is_array($showWhen) || $showWhen === []) {
            return true;
        }

        foreach ($showWhen as $fieldName => $value) {
            if ((string) ($data[$fieldName] ?? '') !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    public function sync(?User $user): array
    {
        $workspace = $this->workspaces->current($user);
        $channel = $this->whatsappChannel($workspace->id);

        abort_unless($channel, 404);

        $phoneResult = $this->settings->enabled('whatsapp_auto_sync_phone_numbers')
            ? $this->syncPhoneNumbers($channel)
            : ['ok' => true, 'synced' => 0];
        $templateResult = $this->settings->enabled('whatsapp_auto_sync_templates')
            ? $this->channels->syncTemplates($channel)
            : ['ok' => true, 'synced' => 0];

        return [
            'ok' => ($phoneResult['ok'] ?? false) && ($templateResult['ok'] ?? false),
            'synced' => (int) ($templateResult['synced'] ?? 0),
            'phone_numbers' => (int) ($phoneResult['synced'] ?? 0),
            'phone_response' => $phoneResult,
            'template_response' => $templateResult,
        ];
    }

    public function connectFromEmbeddedSignup(?User $user, array $data): array
    {
        if (! $this->settings->embeddedSignupReady()) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta Embedded Signup is not configured by the administrator.'),
            ]);
        }

        $workspace = $this->workspaces->current($user);
        $response = $this->client->exchangeEmbeddedSignupCode($data['code']);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta code exchange failed. Please try connecting again.'),
            ]);
        }

        $token = (string) $response->json('access_token');
        $wabaId = $this->firstFilled(
            $data['waba_id'] ?? null,
            data_get($data, 'metadata.waba_id'),
            data_get($data, 'metadata.whatsapp_business_account_id'),
            data_get($data, 'metadata.data.waba_id'),
            data_get($data, 'metadata.data.whatsapp_business_account_id'),
        );
        $phoneNumberId = $this->firstFilled(
            $data['phone_number_id'] ?? null,
            data_get($data, 'metadata.phone_number_id'),
            data_get($data, 'metadata.data.phone_number_id'),
        );

        if (! $wabaId) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta did not return a WhatsApp Business Account ID.'),
            ]);
        }

        $phonePayload = null;

        if (! $phoneNumberId || $this->settings->enabled('whatsapp_auto_sync_phone_numbers')) {
            $phoneResponse = $this->client->phoneNumbers($wabaId, $token);

            if (! $phoneResponse->successful()) {
                throw ValidationException::withMessages([
                    'embedded_signup' => __('Meta phone number sync failed. Check the selected account permissions.'),
                ]);
            }

            $numbers = $phoneResponse->json('data') ?? [];
            $phonePayload = collect($numbers)->first(fn (array $number): bool => ($number['id'] ?? null) === $phoneNumberId) ?? ($numbers[0] ?? null);
            $phoneNumberId = $phoneNumberId ?: ($phonePayload['id'] ?? null);
        }

        if (! $phoneNumberId) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta did not return a WhatsApp phone number.'),
            ]);
        }

        $existing = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'whatsapp')
            ->first();

        if ($existing &&
            (($existing->provider_account_id !== $wabaId) ||
             ($existing->provider_phone_id !== $phoneNumberId))) {
            MessageTemplate::query()
                ->where('workspace_id', $workspace->id)
                ->where('provider', 'whatsapp')
                ->delete();
        }

        $channel = ChannelAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'provider' => 'whatsapp',
            ],
            [
                'name' => $this->firstFilled($phonePayload['verified_name'] ?? null, $data['display_name'] ?? null, 'WhatsApp Business'),
                'status' => ChannelAccountStatus::Connected->value,
                'credentials' => [
                    'access_token' => $token,
                    'source' => 'embedded_signup',
                    'token_type' => $response->json('token_type'),
                    'expires_in' => $response->json('expires_in'),
                ],
                'webhook_verify_token' => $this->settings->get('whatsapp_default_verify_token') ?: Str::random(40),
                'provider_account_id' => $wabaId,
                'provider_phone_id' => $phoneNumberId,
                'provider_display_id' => $phonePayload['display_phone_number'] ?? $data['display_name'] ?? null,
                'settings' => array_filter([
                    'inbox_active' => true,
                    'business_id' => $this->firstFilled($data['business_id'] ?? null, data_get($data, 'metadata.business_id'), data_get($data, 'metadata.data.business_id')),
                    'embedded_signup' => true,
                    'quality_rating' => $phonePayload['quality_rating'] ?? null,
                    'code_verification_status' => $phonePayload['code_verification_status'] ?? null,
                    'platform_type' => $phonePayload['platform_type'] ?? null,
                    'throughput' => $phonePayload['throughput'] ?? null,
                ], fn ($value) => $value !== null),
                'connected_at' => now(),
                'last_synced_at' => now(),
            ]
        );

        $phoneResult = $this->settings->enabled('whatsapp_auto_sync_phone_numbers')
            ? $this->syncPhoneNumbers($channel)
            : ['ok' => true, 'synced' => 1];
        $templateResult = $this->settings->enabled('whatsapp_auto_sync_templates')
            ? $this->channels->syncTemplates($channel)
            : ['ok' => true, 'synced' => 0];

        return [
            'channel' => $channel,
            'phone_numbers' => (int) ($phoneResult['synced'] ?? 0),
            'templates' => (int) ($templateResult['synced'] ?? 0),
        ];
    }

    public function disconnect(?User $user, ?ChannelAccount $channel = null): void
    {
        $workspace = $this->workspaces->current($user);

        if ($channel) {
            abort_unless($channel->workspace_id === $workspace->id, 404);

            $channel->update(['status' => ChannelAccountStatus::Disconnected->value, 'credentials' => null]);

            return;
        }

        ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'whatsapp')
            ->update(['status' => ChannelAccountStatus::Disconnected->value, 'credentials' => null]);
    }

    protected function whatsappChannel(int $workspaceId): ?ChannelAccount
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'whatsapp')
            ->latest()
            ->first();
    }

    protected function workspaceChannels(int $workspaceId): Collection
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('provider', array_keys($this->providerCatalog()))
            ->where('status', '!=', ChannelAccountStatus::Disconnected->value)
            ->latest()
            ->get()
            ->unique('provider')
            ->each(fn (ChannelAccount $channel): ChannelAccount => $channel->setAttribute(
                'webhook_url',
                ($this->providerCatalog()[$channel->provider]['webhook_required'] ?? false) ? $this->webhookUrl($channel) : null
            ))
            ->values();
    }

    protected function syncPhoneNumbers(ChannelAccount $source): array
    {
        $response = $this->client->phoneNumbers((string) $source->provider_account_id, (string) $source->credential('access_token'));
        $numbers = $response->json('data') ?? [];
        $synced = 0;

        foreach ($numbers as $number) {
            $phoneId = $number['id'] ?? null;

            if (! $phoneId) {
                continue;
            }

            ChannelAccount::query()->updateOrCreate(
                [
                    'workspace_id' => $source->workspace_id,
                    'provider' => 'whatsapp',
                ],
                [
                    'name' => $number['verified_name'] ?? $source->name,
                    'status' => ChannelAccountStatus::Connected->value,
                    'credentials' => $source->credentials,
                    'webhook_verify_token' => $source->webhook_verify_token,
                    'provider_account_id' => $source->provider_account_id,
                    'provider_phone_id' => $phoneId,
                    'provider_display_id' => $number['display_phone_number'] ?? $number['verified_name'] ?? null,
                    'settings' => array_filter([
                        'inbox_active' => true,
                        'quality_rating' => $number['quality_rating'] ?? null,
                        'code_verification_status' => $number['code_verification_status'] ?? null,
                        'platform_type' => $number['platform_type'] ?? null,
                        'throughput' => $number['throughput'] ?? null,
                    ], fn ($value) => $value !== null),
                    'connected_at' => $source->connected_at ?? now(),
                    'last_synced_at' => now(),
                ]
            );

            $synced++;
        }

        $source->update(['last_synced_at' => now(), 'status' => $response->successful() ? ChannelAccountStatus::Connected->value : ChannelAccountStatus::Error->value]);

        return ['ok' => $response->successful(), 'synced' => $synced, 'response' => $response->json()];
    }

    protected function webhookUrl(ChannelAccount $channel): string
    {
        $baseUrl = $this->settings->webhookBaseUrl()
            ?: rtrim((string) config('app.url'), '/');

        return $baseUrl.'/webhooks/channels/'.$channel->provider.'/'.$channel->webhook_code;
    }

    protected function webhookUrlForProvider(string $provider): string
    {
        $baseUrl = $this->settings->webhookBaseUrl()
            ?: rtrim((string) config('app.url'), '/');

        if (! ($this->providerCatalog()[$provider]['webhook_required'] ?? false)) {
            return '';
        }

        return $baseUrl.'/webhooks/channels/'.$provider;
    }

    protected function providerCatalog(): array
    {
        return config('marketing-channels.providers', []);
    }

    protected function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = is_string($value) ? trim($value) : $value;

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    protected function validateManualCredentials(int $workspaceId, array $data): array
    {
        $response = $this->client->phoneNumbers($data['waba_id'], $data['access_token']);

        if (! $response->successful()) {
            $this->markExistingManualChannelError($workspaceId, $data['phone_number_id'], $this->metaError($response->json()));

            throw ValidationException::withMessages([
                'access_token' => __('Meta rejected these credentials. Check the WABA ID and access token.'),
            ]);
        }

        $numbers = $response->json('data') ?? [];
        $phonePayload = collect($numbers)->first(fn (array $number): bool => (string) ($number['id'] ?? '') === (string) $data['phone_number_id']);

        if (! $phonePayload) {
            $message = __('The selected Phone Number ID was not found in this WhatsApp Business Account.');
            $this->markExistingManualChannelError($workspaceId, $data['phone_number_id'], $message);

            throw ValidationException::withMessages([
                'phone_number_id' => $message,
            ]);
        }

        return $phonePayload;
    }

    protected function validateMetaSocialCredentials(string $provider, string $accountId, ?string $token): array
    {
        if (blank($token)) {
            return [
                'ok' => false,
                'payload' => null,
                'error' => __('Meta access token is required.'),
            ];
        }

        $response = $this->metaSocial->account(
            $accountId,
            $token,
            $provider === 'instagram' ? ['id', 'username', 'name'] : ['id', 'name']
        );

        if (! $response->successful()) {
            return [
                'ok' => false,
                'payload' => $response->json(),
                'error' => $this->metaError($response->json()),
            ];
        }

        $payload = $response->json() ?? [];

        if ((string) data_get($payload, 'id') !== $accountId) {
            return [
                'ok' => false,
                'payload' => $payload,
                'error' => __('Meta returned a different account than the one requested.'),
            ];
        }

        return [
            'ok' => true,
            'payload' => $payload,
            'error' => null,
        ];
    }

    protected function markExistingManualChannelError(int $workspaceId, string $phoneNumberId, string $message): void
    {
        ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'whatsapp')
            ->where('provider_phone_id', $phoneNumberId)
            ->update([
                'status' => ChannelAccountStatus::Error->value,
                'settings->last_error' => Str::limit($message, 1000),
            ]);
    }

    protected function metaError(?array $payload): string
    {
        return (string) data_get($payload, 'error.message', __('Meta credential validation failed.'));
    }
}
