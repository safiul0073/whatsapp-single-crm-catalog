<?php

namespace App\Modules\WhatsAppCloud\Services;

use App\Models\User;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ChannelReadinessService;
use App\Modules\MarketingChannels\Services\ChannelSetupSteps;
use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MetaSocial\Services\MetaSocialClient;
use App\Modules\Telegram\Services\TelegramBotProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChannelSetupService
{
    /**
     * Meta channels connect only through Meta login (Embedded Signup / OAuth), never with pasted tokens.
     */
    public const META_LOGIN_PROVIDERS = ['whatsapp', 'messenger', 'instagram', 'threads'];

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
        $channel = $this->whatsappChannel($workspace->id);
        $approvedTemplatesCount = MessageTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'whatsapp')
            ->where('status', MessageTemplateStatus::Approved->value)
            ->count();

        return [
            'workspace' => $workspace,
            'provider' => $this->providerCatalog()['whatsapp'] ?? [],
            'channel' => $channel,
            'steps' => $this->setupSteps($channel),
            'approvedTemplatesCount' => $approvedTemplatesCount,
            'catalogs' => Catalog::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_active', true)
                ->latest()
                ->get(),
            'webhookUrl' => $this->webhookUrlForProvider('whatsapp'),
            'embeddedSignup' => $this->embeddedSignupConfig(),
            'readinessReport' => $channel ? app(ChannelReadinessService::class)->report($channel) : null,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, state: string}>
     */
    public function setupSteps(?ChannelAccount $channel): array
    {
        $isConnected = $channel?->status === ChannelAccountStatus::Connected;
        $settings = $channel?->settings ?? [];
        $hasError = filled($settings['last_error'] ?? null);

        return ChannelSetupSteps::build([
            [
                'key' => 'gather',
                'label' => __('Gather your Meta details'),
                'description' => __('Have the WhatsApp Business Account ID, Business ID, Phone Number ID and a System User token ready.'),
                'done' => $channel !== null,
            ],
            [
                'key' => 'connect',
                'label' => __('Connect your number'),
                'description' => __('Use Connect with Meta, or enter the details manually. We validate the token against Meta.'),
                'done' => $isConnected && filled($channel?->credential('access_token')),
            ],
            [
                'key' => 'webhook',
                'label' => __('Configure the webhook in Meta'),
                'description' => __('Paste the webhook URL and verify token into your Meta app, then send a test message so we receive the first event.'),
                'done' => $isConnected && filled($channel?->webhook_verify_token) && ($channel?->webhookEvents()->exists() ?? false),
            ],
            [
                'key' => 'verify',
                'label' => __('Test and sync'),
                'description' => __('Run a connection test and sync phone numbers and templates from Meta.'),
                'done' => $isConnected && $channel?->last_synced_at !== null && ! $hasError,
            ],
        ]);
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

    public function connectGeneric(?User $user, array $data): ChannelAccount
    {
        $provider = (string) $data['provider'];
        $providers = $this->providerCatalog();

        abort_unless(isset($providers[$provider]) && ! in_array($provider, self::META_LOGIN_PROVIDERS, true), 404);

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
        $displayId = $this->firstFilled($data['provider_display_id'] ?? null);
        $providerAccountId = $this->firstFilled(
            $data['provider_account_id'] ?? null,
            $credentials[$providers[$provider]['credential_provider_field'] ?? ''] ?? null,
            $existing?->provider_account_id,
            $provider
        );
        $connectionTest = $this->testGenericConnection(
            $provider,
            $workspace->id,
            $data['name'],
            $credentials,
            $providerAccountId,
            $displayId,
            $verifyToken,
        );
        $status = ($connectionTest['ok'] ?? false)
            ? ChannelAccountStatus::Connected
            : ChannelAccountStatus::Error;
        $lastError = $connectionTest['error'] ?? null;

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

        if (blank($channel->credential('access_token')) || $channel->status === ChannelAccountStatus::Disconnected) {
            return [
                'ok' => false,
                'synced' => 0,
                'phone_numbers' => 0,
                'error' => __('Channel is disconnected. Please reconnect with Meta or enter valid credentials before syncing.'),
            ];
        }

        $phoneResult = $this->settings->enabled('whatsapp_auto_sync_phone_numbers')
            ? $this->syncPhoneNumbers($channel)
            : ['ok' => true, 'synced' => 0];

        $this->subscribeToWebhooks($channel);
        $templateResult = $this->settings->enabled('whatsapp_auto_sync_templates')
            ? $this->channels->syncTemplates($channel)
            : ['ok' => true, 'synced' => 0];

        $ok = ($phoneResult['ok'] ?? false) && ($templateResult['ok'] ?? false);

        return [
            'ok' => $ok,
            'synced' => (int) ($templateResult['synced'] ?? 0),
            'phone_numbers' => (int) ($phoneResult['synced'] ?? 0),
            'error' => ! $ok ? ($phoneResult['error'] ?? $templateResult['error'] ?? __('Meta sync failed. Check credentials.')) : null,
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

        $code = $data['code'];
        $tokenMeta = [];

        if (str_starts_with($code, 'EA')) {
            $token = $code;
        } else {
            $response = $this->client->exchangeEmbeddedSignupCode($code);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw ValidationException::withMessages([
                    'embedded_signup' => __('Meta code exchange failed. Please try connecting again. '.$response->body()),
                ]);
            }

            $token = (string) $response->json('access_token');
            $tokenMeta = [
                'token_type' => $response->json('token_type'),
                'expires_in' => $response->json('expires_in'),
            ];
        }

        return $this->connectWithToken($user, $token, $data, $tokenMeta);
    }

    /**
     * Connects a WABA phone number with a token already in hand, e.g. from Embedded Signup or the
     * "Connect your Meta Business" flow.
     */
    public function connectWithToken(?User $user, string $token, array $data, array $tokenMeta = [], string $source = 'embedded_signup'): array
    {
        $workspace = $this->workspaces->current($user);
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
                'credentials' => array_merge($existing?->credentials ?? [], [
                    'access_token' => $token,
                    'source' => $source,
                ], $tokenMeta),
                'webhook_verify_token' => $this->settings->get('whatsapp_default_verify_token') ?: Str::random(40),
                'provider_account_id' => $wabaId,
                'provider_phone_id' => $phoneNumberId,
                'provider_display_id' => $phonePayload['display_phone_number'] ?? $data['display_name'] ?? null,
                'settings' => array_filter([
                    'inbox_active' => true,
                    'business_id' => $this->firstFilled($data['business_id'] ?? null, data_get($data, 'metadata.business_id'), data_get($data, 'metadata.data.business_id')),
                    'embedded_signup' => true,
                    'signup_mode' => $data['signup_mode'] ?? null,
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

        if (($data['signup_mode'] ?? null) === 'new') {
            $this->registerPhoneNumber($channel);
        }

        $this->subscribeToWebhooks($channel);
        $templateResult = $this->settings->enabled('whatsapp_auto_sync_templates')
            ? $this->channels->syncTemplates($channel)
            : ['ok' => true, 'synced' => 0];

        $channel->refresh();

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

    public function destroy(?User $user, ?ChannelAccount $channel = null): void
    {
        $workspace = $this->workspaces->current($user);

        if ($channel) {
            abort_unless($channel->workspace_id === $workspace->id, 404);

            $channel->delete();

            return;
        }

        ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'whatsapp')
            ->delete();
    }

    protected function whatsappChannel(int $workspaceId): ?ChannelAccount
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'whatsapp')
            ->latest()
            ->first();
    }

    protected function syncPhoneNumbers(ChannelAccount $source): array
    {
        $token = (string) $source->credential('access_token');
        if (blank($token)) {
            return ['ok' => false, 'synced' => 0, 'error' => __('Access token is missing.')];
        }

        $response = $this->client->phoneNumbers((string) $source->provider_account_id, $token);
        $numbers = $response->json('data') ?? [];
        $synced = 0;

        $otherNumbers = [];

        foreach ($numbers as $number) {
            $phoneId = $number['id'] ?? null;

            if (! $phoneId) {
                continue;
            }

            $synced++;

            if ((string) $phoneId !== (string) $source->provider_phone_id) {
                $otherNumbers[] = array_filter([
                    'id' => (string) $phoneId,
                    'display_phone_number' => $number['display_phone_number'] ?? null,
                    'verified_name' => $number['verified_name'] ?? null,
                ], fn ($value) => $value !== null);

                continue;
            }

            $source->fill([
                'name' => $number['verified_name'] ?? $source->name,
                'provider_display_id' => $number['display_phone_number'] ?? $number['verified_name'] ?? $source->provider_display_id,
                'settings' => array_merge($source->settings ?? [], array_filter([
                    'quality_rating' => $number['quality_rating'] ?? null,
                    'code_verification_status' => $number['code_verification_status'] ?? null,
                    'platform_type' => $number['platform_type'] ?? null,
                    'throughput' => $number['throughput'] ?? null,
                ], fn ($value) => $value !== null)),
            ]);
        }

        $settings = $source->settings ?? [];
        if (! $response->successful()) {
            $settings['last_error'] = $this->metaError($response->json());
        } else {
            unset($settings['last_error']);
            $settings['other_phone_numbers'] = $otherNumbers;
        }

        $source->update([
            'last_synced_at' => now(),
            'status' => $response->successful() ? ChannelAccountStatus::Connected->value : ChannelAccountStatus::Error->value,
            'settings' => $settings,
        ]);

        return [
            'ok' => $response->successful(),
            'synced' => $synced,
            'error' => ! $response->successful() ? $this->metaError($response->json()) : null,
            'response' => $response->json(),
        ];
    }

    protected function subscribeToWebhooks(ChannelAccount $channel): void
    {
        $response = $this->client->subscribeApp(
            (string) $channel->provider_account_id,
            (string) $channel->credential('access_token'),
            $this->webhookUrl($channel),
            (string) $channel->webhook_verify_token,
        );

        if (! $response->successful()) {
            $error = $this->metaError($response->json());

            Log::warning('WhatsApp webhook subscription failed.', [
                'channel_account_id' => $channel->id,
                'error' => $error,
            ]);

            $channel->update([
                'status' => ChannelAccountStatus::Error->value,
                'settings' => array_merge($channel->settings ?? [], [
                    'last_error' => Str::limit(__('Webhook subscription failed: :error', ['error' => $error]), 1000),
                ]),
            ]);
        }
    }

    /**
     * Registers a number created through Embedded Signup's "new number" flow so it can send.
     * Skipped once a number is registered, because Meta rate-limits repeat register calls.
     */
    protected function registerPhoneNumber(ChannelAccount $channel): void
    {
        $settings = $channel->settings ?? [];

        if (($settings['registered_phone_id'] ?? null) === $channel->provider_phone_id) {
            return;
        }

        $credentials = $channel->credentials ?? [];
        $pin = (string) ($credentials['two_step_pin'] ?? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT));

        $response = $this->client->registerPhoneNumber(
            (string) $channel->provider_phone_id,
            (string) $channel->credential('access_token'),
            $pin,
        );

        if (! $response->successful()) {
            $error = $this->metaError($response->json());

            Log::warning('WhatsApp phone number registration failed.', [
                'channel_account_id' => $channel->id,
                'error' => $error,
            ]);

            $channel->update([
                'status' => ChannelAccountStatus::Error->value,
                'settings' => array_merge($settings, [
                    'last_error' => Str::limit(__('Phone number registration failed: :error', ['error' => $error]), 1000),
                ]),
            ]);

            return;
        }

        $channel->update([
            'credentials' => array_merge($credentials, ['two_step_pin' => $pin]),
            'settings' => array_merge($settings, ['registered_phone_id' => $channel->provider_phone_id]),
        ]);
    }

    protected function webhookUrl(ChannelAccount $channel): string
    {
        $baseUrl = $this->settings->webhookBaseUrl()
            ?: rtrim((string) config('app.url'), '/');

        return $baseUrl.'/webhooks/channels/'.$channel->provider.'/'.$channel->webhook_code;
    }

    public function webhookUrlForProvider(string $provider): string
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

    protected function metaError(?array $payload): string
    {
        return app(MetaErrorTranslator::class)->summary($payload, __('Meta credential validation failed.'));
    }
}
