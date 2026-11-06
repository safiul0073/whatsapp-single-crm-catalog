<?php

namespace App\Modules\MetaSocial\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelSetupSteps;
use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetaSocialSetupService
{
    protected const PENDING_SESSION_KEY = 'meta_social.pending_accounts';

    /**
     * Page webhook fields needed for Messenger and Instagram DMs to reach the shared inbox.
     */
    public const PAGE_WEBHOOK_FIELDS = 'messages,messaging_postbacks,message_deliveries,message_reads,messaging_referrals,messaging_optins';

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected MetaSocialClient $client,
        protected MetaSocialSettingsService $settings,
    ) {}

    public function pageData(?User $user): array
    {
        $workspace = $this->workspaces->current($user);

        $messengerAccounts = $this->accounts($workspace->id, 'messenger');
        $instagramAccounts = $this->accounts($workspace->id, 'instagram');
        $threadsAccounts = $this->accounts($workspace->id, 'threads');

        return [
            'workspace' => $workspace,
            'messengerAccounts' => $messengerAccounts,
            'instagramAccounts' => $instagramAccounts,
            'threadsAccounts' => $threadsAccounts,
            'messengerSignup' => $this->embeddedSignupConfig('messenger'),
            'instagramSignup' => $this->embeddedSignupConfig('instagram'),
            'pendingSelection' => $this->pendingSelection(),
            'threadsLoginReady' => app(MetaAppSettingsService::class)->filled('meta_threads_app_id') !== null
                && app(MetaAppSettingsService::class)->filled('meta_threads_app_secret') !== null,
            'sections' => [
                $this->section('messenger', $messengerAccounts, $this->embeddedSignupConfig('messenger')),
                $this->section('instagram', $instagramAccounts, $this->embeddedSignupConfig('instagram')),
                $this->section('threads', $threadsAccounts, null),
            ],
        ];
    }

    /**
     * @param  Collection<int, ChannelAccount>  $accounts
     * @return array<string, mixed>
     */
    protected function section(string $provider, Collection $accounts, ?array $signup): array
    {
        return [
            'key' => $provider,
            'config' => config("marketing-channels.providers.{$provider}", []),
            'accounts' => $accounts,
            'primary' => $accounts->first(fn (ChannelAccount $account): bool => $account->status === ChannelAccountStatus::Connected) ?? $accounts->first(),
            'signup' => $signup,
            'steps' => $this->setupSteps($provider, $accounts),
            'webhook_url' => $provider === 'threads' ? $this->threadsWebhookUrl() : null,
        ];
    }

    /**
     * @param  Collection<int, ChannelAccount>  $accounts
     * @return array<int, array{key: string, label: string, description: string, state: string}>
     */
    protected function setupSteps(string $provider, Collection $accounts): array
    {
        $connected = $accounts->filter(fn (ChannelAccount $account): bool => $account->status === ChannelAccountStatus::Connected);
        $hasError = $accounts->contains(fn (ChannelAccount $account): bool => filled(data_get($account->settings, 'last_error')));
        $hasEvents = $connected->contains(fn (ChannelAccount $account): bool => $account->webhookEvents()->exists());

        $labels = match ($provider) {
            'messenger' => [__('Prepare your Facebook Page'), __('You need admin access to the Page and a Page access token.')],
            'instagram' => [__('Prepare your Instagram Business account'), __('Link the account to a Facebook Page and note the account ID.')],
            default => [__('Prepare your Threads account'), __('Note the Threads account ID and a long-lived access token.')],
        };

        return ChannelSetupSteps::build([
            ['key' => 'prepare', 'label' => $labels[0], 'description' => $labels[1], 'done' => $accounts->isNotEmpty()],
            ['key' => 'connect', 'label' => __('Connect the account'), 'description' => __('Save the account details. We validate them before marking the channel connected.'), 'done' => $connected->isNotEmpty()],
            ['key' => 'webhook', 'label' => __('Receive webhook events'), 'description' => __('Add the webhook URL to your Meta app, then send a test message.'), 'done' => $hasEvents, 'skipped' => $provider === 'threads' && $connected->isEmpty()],
            ['key' => 'test', 'label' => __('Test the connection'), 'description' => __('Run a connection test and resolve any token errors.'), 'done' => $connected->isNotEmpty() && ! $hasError],
        ]);
    }

    protected function threadsWebhookUrl(): string
    {
        $baseUrl = $this->settings->webhookBaseUrl() ?: rtrim((string) config('app.url'), '/');

        return $baseUrl.'/webhooks/channels/threads';
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

    /**
     * Exchanges a Facebook Login for Business code once and lists the Pages (Messenger) or linked
     * Instagram accounts the user granted, so they can pick which ones to connect.
     *
     * @return array<int, array{id: string, name: string, page_id: string, page_name: string, username?: string}>
     */
    public function discoverAccounts(string $provider, string $code): array
    {
        $this->ensureProvider($provider);

        if (! $this->settings->embeddedSignupReady($provider)) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta Embedded Signup is not configured by the administrator.'),
            ]);
        }

        $userToken = $this->longLivedToken($this->accessToken($code));
        $accounts = $this->grantedAccounts($provider, $userToken);

        if ($accounts === []) {
            throw ValidationException::withMessages([
                'embedded_signup' => $provider === 'instagram'
                    ? __('Meta did not share any Instagram Business account. Link Instagram to a Facebook Page and select it during login.')
                    : __('Meta did not share any Facebook Page. Select at least one Page during login.'),
            ]);
        }

        session()->put(self::PENDING_SESSION_KEY, [
            'provider' => $provider,
            'user_token' => Crypt::encryptString($userToken),
            'accounts' => $accounts,
            'expires_at' => now()->addMinutes(30)->getTimestamp(),
        ]);

        return $accounts;
    }

    /**
     * The accounts waiting for the user to pick, without the token.
     *
     * @return array{provider: string, accounts: array<int, array<string, string>>}|null
     */
    public function pendingSelection(): ?array
    {
        $pending = session(self::PENDING_SESSION_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return ['provider' => (string) $pending['provider'], 'accounts' => $pending['accounts'] ?? []];
    }

    public function forgetPendingSelection(): void
    {
        session()->forget(self::PENDING_SESSION_KEY);
    }

    /**
     * Connects the accounts the user ticked from the last discovery, each with its own Page token.
     *
     * @param  array<int, string>  $accountIds
     * @return Collection<int, ChannelAccount>
     */
    public function connectSelected(?User $user, string $provider, array $accountIds): Collection
    {
        $this->ensureProvider($provider);
        $pending = session(self::PENDING_SESSION_KEY);

        if (! is_array($pending) || $pending['provider'] !== $provider || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('This Meta login has expired. Please connect again.'),
            ]);
        }

        $channels = $this->connectGrantedAccounts($user, $provider, Crypt::decryptString((string) $pending['user_token']), $accountIds);

        $this->forgetPendingSelection();

        return $channels;
    }

    /**
     * Connects the chosen Pages or Instagram accounts that this user token can access, each with its own Page token.
     *
     * @param  array<int, string>  $accountIds
     * @return Collection<int, ChannelAccount>
     */
    public function connectGrantedAccounts(?User $user, string $provider, string $userToken, array $accountIds, string $source = 'embedded_signup'): Collection
    {
        $this->ensureProvider($provider);
        $workspace = $this->workspaces->current($user);
        $granted = collect($this->grantedAccounts($provider, $userToken, withTokens: true))->keyBy('id');
        $selected = collect($accountIds)->map(fn ($id): string => (string) $id)->unique()->filter(fn (string $id): bool => $granted->has($id));

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'account_ids' => __('Select at least one account to connect.'),
            ]);
        }

        return $selected->map(function (string $id) use ($granted, $workspace, $provider, $userToken, $source): ChannelAccount {
            $account = $granted->get($id);

            return $this->persistAccount($workspace, $provider, $id, (string) ($account['access_token'] ?? $userToken), [
                'page_id' => $account['page_id'],
                'page_name' => $account['page_name'],
                'instagram_account_id' => $provider === 'instagram' ? $id : null,
                'username' => $account['username'] ?? null,
                'display_name' => $provider === 'instagram' ? ($account['username'] ?? $account['name']) : $account['name'],
            ], $source);
        })->values();
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function grantedAccounts(string $provider, string $userToken, bool $withTokens = false): array
    {
        $response = $this->client->pageAccounts($userToken);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'embedded_signup' => app(MetaErrorTranslator::class)->summary($response->json(), __('Meta could not list your Pages. Please try connecting again.')),
            ]);
        }

        return collect($response->json('data', []))
            ->map(function (array $page) use ($provider, $withTokens): ?array {
                $pageId = (string) ($page['id'] ?? '');
                $pageName = (string) ($page['name'] ?? '');

                if ($pageId === '') {
                    return null;
                }

                if ($provider === 'instagram') {
                    $instagram = $page['instagram_business_account'] ?? null;

                    if (blank($instagram['id'] ?? null)) {
                        return null;
                    }

                    $account = [
                        'id' => (string) $instagram['id'],
                        'name' => (string) ($instagram['name'] ?? $instagram['username'] ?? $pageName),
                        'username' => (string) ($instagram['username'] ?? ''),
                        'page_id' => $pageId,
                        'page_name' => $pageName,
                    ];
                } else {
                    $account = ['id' => $pageId, 'name' => $pageName, 'page_id' => $pageId, 'page_name' => $pageName];
                }

                if ($withTokens) {
                    $account['access_token'] = (string) ($page['access_token'] ?? '');
                }

                return $account;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function persistAccount(Workspace $workspace, string $provider, string $providerAccountId, string $token, array $data, string $source): ChannelAccount
    {
        $validation = $this->validateAccount($provider, $providerAccountId, $token);
        $status = ($validation['ok'] ?? false) ? ChannelAccountStatus::Connected : ChannelAccountStatus::Error;
        $displayId = $this->firstFilled(
            $provider === 'instagram'
                ? ($data['username'] ?? $data['display_name'] ?? null)
                : ($data['page_name'] ?? $data['display_name'] ?? null),
            data_get($validation, $provider === 'instagram' ? 'payload.username' : 'payload.name')
        );

        $existingVerifyToken = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', $provider)
            ->where('provider_account_id', $providerAccountId)
            ->value('webhook_verify_token');

        $channel = ChannelAccount::query()->updateOrCreate(
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
                    'source' => $source,
                ],
                'webhook_verify_token' => $this->settings->get('meta_social_default_verify_token') ?: ($existingVerifyToken ?: Str::random(40)),
                'provider_phone_id' => $data['page_id'] ?? null,
                'provider_display_id' => $displayId,
                'settings' => array_filter([
                    'inbox_active' => true,
                    'embedded_signup' => in_array($source, ['embedded_signup', 'meta_business'], true),
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

        if ($status === ChannelAccountStatus::Connected) {
            $this->subscribePage($channel, $token);
        }

        return $channel;
    }

    /**
     * Page webhooks are only delivered for Pages subscribed to the app, so subscribe on connect
     * and surface a failure on the channel instead of silently missing messages.
     */
    protected function subscribePage(ChannelAccount $channel, string $token): void
    {
        $pageId = $this->firstFilled(data_get($channel->settings, 'page_id'), $channel->provider_phone_id, $channel->provider === 'messenger' ? $channel->provider_account_id : null);

        if (! $pageId) {
            return;
        }

        try {
            $response = $this->client->subscribePage($pageId, $token, self::PAGE_WEBHOOK_FIELDS);
            $error = $response->successful() ? null : app(MetaErrorTranslator::class)->summary($response->json(), __('Meta rejected the request.'));
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        $settings = $channel->settings ?? [];

        if ($error === null) {
            $channel->update(['settings' => array_merge($settings, ['webhook_subscribed_at' => now()->toIso8601String()])]);

            return;
        }

        Log::warning('Meta Page webhook subscription failed.', ['channel_account_id' => $channel->id, 'error' => $error]);

        $channel->update([
            'status' => ChannelAccountStatus::Error->value,
            'settings' => array_merge($settings, [
                'last_error' => Str::limit(__('Webhook subscription failed: :error', ['error' => $error]), 1000),
            ]),
        ]);
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
            ->each(function (ChannelAccount $account) use ($provider): void {
                $account->setAttribute('webhook_url', $this->webhookUrl($account));

                if ($provider === 'messenger') {
                    $offers = app(MessengerMarketingService::class);
                    $account->setAttribute('offer_settings', $offers->settings($account));
                    $account->setAttribute('offer_subscribers', $offers->subscriberCount($account));
                }
            });
    }

    protected function accessToken(string $code): string
    {
        $response = $this->client->exchangeEmbeddedSignupCode($code);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw ValidationException::withMessages([
                'embedded_signup' => __('Meta code exchange failed. Please try connecting again.'),
            ]);
        }

        return (string) $response->json('access_token');
    }

    /**
     * Swaps the short-lived user token from the signup code for a long-lived one, keeping the original if Meta refuses.
     */
    protected function longLivedToken(string $token): string
    {
        try {
            $response = $this->client->exchangeLongLivedToken($token);
        } catch (\Throwable) {
            return $token;
        }

        return $response->successful() && filled($response->json('access_token'))
            ? (string) $response->json('access_token')
            : $token;
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
                'error' => app(MetaErrorTranslator::class)->summary($response->json(), __('Meta credential validation failed.')),
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
