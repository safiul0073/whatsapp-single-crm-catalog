<?php

namespace App\Modules\Threads\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Services\MetaAppSettingsService;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connects a Threads account through Threads' own OAuth login, so nobody pastes IDs or tokens.
 */
class ThreadsConnectService
{
    protected const STATE_KEY = 'threads_oauth_state';

    public function __construct(
        protected ThreadsClient $client,
        protected MetaAppSettingsService $metaApp,
        protected MetaSocialSettingsService $social,
        protected WorkspaceResolver $workspaces,
    ) {}

    public function ready(): bool
    {
        return $this->metaApp->filled('meta_threads_app_id') !== null
            && $this->metaApp->filled('meta_threads_app_secret') !== null;
    }

    public function authorizationUrl(): string
    {
        $this->ensureReady();

        $state = Str::random(40);
        session([self::STATE_KEY => $state]);

        return $this->client->authorizeUrl((string) $this->metaApp->filled('meta_threads_app_id'), $this->redirectUri(), $state);
    }

    public function connect(?User $user, string $code, string $state): ChannelAccount
    {
        $expected = (string) session()->pull(self::STATE_KEY, '');

        if ($expected === '' || ! hash_equals($expected, $state)) {
            throw ValidationException::withMessages(['threads' => __('The Threads sign-in expired. Please try again.')]);
        }

        $this->ensureReady();

        $appId = (string) $this->metaApp->filled('meta_threads_app_id');
        $appSecret = (string) $this->metaApp->filled('meta_threads_app_secret');
        $response = $this->client->exchangeCode($appId, $appSecret, $this->redirectUri(), Str::before($code, '#'));
        $token = (string) $response->json('access_token', '');

        if (! $response->successful() || $token === '') {
            throw ValidationException::withMessages(['threads' => __('Threads did not accept the sign-in. Please try again.')]);
        }

        $longLived = $this->client->exchangeLongLivedToken($appSecret, $token);
        $token = $longLived->successful() && filled($longLived->json('access_token')) ? (string) $longLived->json('access_token') : $token;

        $profile = $this->client->me($token);
        $threadsUserId = (string) ($profile->json('id') ?? $response->json('user_id') ?? '');

        if (! $profile->successful() || $threadsUserId === '') {
            throw ValidationException::withMessages(['threads' => __('Threads did not share the account profile. Please try again.')]);
        }

        $username = (string) ($profile->json('username') ?? '');
        $workspace = $this->workspaces->current($user);
        $existing = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'threads')
            ->first();

        return ChannelAccount::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'provider' => 'threads'],
            [
                'name' => $username !== '' ? 'Threads @'.$username : 'Threads',
                'status' => ChannelAccountStatus::Connected->value,
                'credentials' => ['access_token' => $token, 'source' => 'threads_login'],
                'webhook_verify_token' => $existing?->webhook_verify_token ?: ($this->social->get('meta_social_default_verify_token') ?: Str::random(40)),
                'provider_account_id' => $threadsUserId,
                'provider_phone_id' => null,
                'provider_display_id' => $username !== '' ? '@'.$username : null,
                'settings' => [
                    'inbox_active' => true,
                    'capabilities' => config('marketing-channels.providers.threads.capabilities', []),
                    'username' => $username,
                    'token_expires_at' => filled($longLived->json('expires_in')) ? now()->addSeconds((int) $longLived->json('expires_in'))->toISOString() : null,
                ],
                'connected_at' => now(),
                'last_synced_at' => now(),
            ]
        );
    }

    public function redirectUri(): string
    {
        return route('user.threads.callback');
    }

    protected function ensureReady(): void
    {
        if (! $this->ready()) {
            throw ValidationException::withMessages(['threads' => __('Threads login is not configured by the administrator.')]);
        }
    }
}
