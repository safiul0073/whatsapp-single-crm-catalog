<?php

namespace App\Modules\MetaSocial\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetaSocialSetupService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected MetaSocialClient $client,
        protected MetaSocialSettingsService $settings,
    ) {}

    public function pageData(?User $user): array
    {
        $workspace = $this->workspaces->current($user);

        return [
            'workspace' => $workspace,
            'messengerAccounts' => $this->accounts($workspace->id, 'messenger'),
            'instagramAccounts' => $this->accounts($workspace->id, 'instagram'),
            'messengerSignup' => $this->embeddedSignupConfig('messenger'),
            'instagramSignup' => $this->embeddedSignupConfig('instagram'),
        ];
    }

    public function embeddedSignupConfig(string $provider): array
    {
        $configKey = $provider === 'instagram' ? 'meta_social_instagram_config_id' : 'meta_social_messenger_config_id';

        return [
            'provider' => $provider,
            'enabled' => $this->settings->embeddedSignupReady($provider),
            'app_id' => $this->settings->get('meta_social_app_id', ''),
            'config_id' => $this->settings->get($configKey, ''),
            'graph_api_version' => $this->settings->graphApiVersion(),
        ];
    }

    public function connectFromEmbeddedSignup(?User $user, string $provider, array $data): ChannelAccount
    {
        $this->ensureProvider($provider);

        if (! $this->settings->embeddedSignupReady($provider) && blank($data['access_token'] ?? null)) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta Embedded Signup is not configured by the administrator.'),
            ]);
        }

        $workspace = $this->workspaces->current($user);
        $token = $this->accessToken($data);
        $providerAccountId = $this->providerAccountId($provider, $data);

        if (! $providerAccountId) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta did not return a connected account.'),
            ]);
        }

        $validation = $this->validateAccount($provider, $providerAccountId, $token);
        $status = ($validation['ok'] ?? false) ? ChannelAccountStatus::Connected : ChannelAccountStatus::Error;
        $displayId = $this->firstFilled(
            $provider === 'instagram'
                ? ($data['username'] ?? $data['display_name'] ?? null)
                : ($data['page_name'] ?? $data['display_name'] ?? null),
            data_get($validation, $provider === 'instagram' ? 'payload.username' : 'payload.name')
        );

        return ChannelAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'provider' => $provider,
                'provider_account_id' => $providerAccountId,
            ],
            [
                'name' => $this->displayName($provider, $data),
                'status' => $status->value,
                'credentials' => [
                    'access_token' => $token,
                    'source' => filled($data['code'] ?? null) ? 'embedded_signup' : 'manual_token',
                ],
                'webhook_verify_token' => $this->settings->get('meta_social_default_verify_token') ?: Str::random(40),
                'provider_phone_id' => $data['page_id'] ?? null,
                'provider_display_id' => $displayId,
                'settings' => array_filter([
                    'inbox_active' => true,
                    'embedded_signup' => filled($data['code'] ?? null),
                    'page_id' => $data['page_id'] ?? null,
                    'page_name' => $data['page_name'] ?? null,
                    'instagram_account_id' => $data['instagram_account_id'] ?? null,
                    'username' => $data['username'] ?? null,
                    'last_error' => $validation['error'] ?? null,
                    'metadata' => $data['metadata'] ?? [],
                ], fn ($value) => $value !== null && $value !== ''),
                'connected_at' => now(),
                'last_synced_at' => now(),
            ]
        );
    }

    public function disconnect(?User $user, ChannelAccount $channel): void
    {
        $workspace = $this->workspaces->current($user);

        abort_unless($channel->workspace_id === $workspace->id && in_array($channel->provider, ['messenger', 'instagram'], true), 404);

        $channel->update([
            'status' => ChannelAccountStatus::Disconnected->value,
            'credentials' => null,
        ]);
    }

    public function webhookUrl(ChannelAccount $account): string
    {
        $baseUrl = $this->settings->webhookBaseUrl();

        if ($baseUrl) {
            return $baseUrl.'/webhooks/meta/'.$account->webhook_verify_token;
        }

        return route('webhooks.meta.receive', $account->webhook_verify_token);
    }

    protected function accounts(int $workspaceId, string $provider): Collection
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', $provider)
            ->latest()
            ->get()
            ->each(fn (ChannelAccount $account) => $account->setAttribute('webhook_url', $this->webhookUrl($account)));
    }

    protected function accessToken(array $data): string
    {
        if (filled($data['access_token'] ?? null)) {
            return (string) $data['access_token'];
        }

        $response = $this->client->exchangeEmbeddedSignupCode((string) ($data['code'] ?? ''));

        if (! $response->successful() || ! $response->json('access_token')) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta code exchange failed. Please try connecting again.'),
            ]);
        }

        return (string) $response->json('access_token');
    }

    protected function providerAccountId(string $provider, array $data): ?string
    {
        if ($provider === 'instagram') {
            return $this->firstFilled($data['instagram_account_id'] ?? null, $data['provider_account_id'] ?? null);
        }

        return $this->firstFilled($data['page_id'] ?? null, $data['provider_account_id'] ?? null);
    }

    protected function displayName(string $provider, array $data): string
    {
        return $this->firstFilled(
            $data['display_name'] ?? null,
            $provider === 'instagram' ? ($data['username'] ?? null) : ($data['page_name'] ?? null),
            $provider === 'instagram' ? 'Instagram Business' : 'Messenger Page'
        ) ?? 'Meta Channel';
    }

    protected function ensureProvider(string $provider): void
    {
        abort_unless(in_array($provider, ['messenger', 'instagram'], true), 404);
    }

    protected function validateAccount(string $provider, string $accountId, string $token): array
    {
        if (blank($token)) {
            return [
                'ok' => false,
                'payload' => null,
                'error' => __('Meta access token is required.'),
            ];
        }

        $response = $this->client->account(
            $accountId,
            $token,
            $provider === 'instagram' ? ['id', 'username', 'name'] : ['id', 'name']
        );

        if (! $response->successful()) {
            return [
                'ok' => false,
                'payload' => $response->json(),
                'error' => (string) data_get($response->json(), 'error.message', __('Meta credential validation failed.')),
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
}
