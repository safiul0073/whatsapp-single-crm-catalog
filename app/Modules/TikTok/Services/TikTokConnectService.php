<?php

namespace App\Modules\TikTok\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TikTokConnectService
{
    protected const STATE_KEY = 'tiktok_oauth_state';

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected TikTokClient $client,
        protected TikTokSettingsService $settings,
    ) {}

    public function redirectUri(): string
    {
        return route('user.tiktok.callback');
    }

    public function authorizationUrl(): string
    {
        if (! $this->settings->ready()) {
            throw ValidationException::withMessages(['tiktok' => __('TikTok is not configured by the administrator.')]);
        }

        $state = Str::random(40);
        session([self::STATE_KEY => $state]);

        return $this->client->authorizeUrl($state, $this->redirectUri());
    }

    public function connect(?User $user, string $code, string $state): ChannelAccount
    {
        $expected = (string) session()->pull(self::STATE_KEY, '');

        if ($expected === '' || ! hash_equals($expected, $state)) {
            throw ValidationException::withMessages(['tiktok' => __('The TikTok sign-in expired. Please try again.')]);
        }

        $response = $this->client->exchangeCode($code, $this->redirectUri());
        $tokens = $this->tokens($response);
        $openId = (string) ($tokens['open_id'] ?? '');

        if ($openId === '') {
            throw ValidationException::withMessages(['tiktok' => __('TikTok did not return an account.')]);
        }

        $profile = $this->client->userInfo($tokens['access_token'])->json('data.user') ?? [];
        $workspace = $this->workspaces->current($user);
        $displayName = (string) ($profile['display_name'] ?? '') ?: 'TikTok account';

        return ChannelAccount::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'provider' => 'tiktok', 'provider_account_id' => $openId],
            [
                'name' => $displayName,
                'status' => ChannelAccountStatus::Connected->value,
                'credentials' => $tokens,
                'webhook_verify_token' => Str::random(40),
                'provider_display_id' => $displayName,
                'settings' => array_filter(['avatar_url' => $profile['avatar_url'] ?? null, 'union_id' => $profile['union_id'] ?? null, 'scope' => $tokens['scope'] ?? null]),
                'connected_at' => now(),
                'last_synced_at' => now(),
            ]
        );
    }

    public function disconnect(?User $user, ChannelAccount $channel): void
    {
        $workspace = $this->workspaces->current($user);

        abort_unless($channel->workspace_id === $workspace->id && $channel->provider === 'tiktok', 404);

        $channel->update(['status' => ChannelAccountStatus::Disconnected->value, 'credentials' => null]);
    }

    /**
     * Normalizes a token response into the credentials stored on the channel account.
     *
     * @return array{access_token: string, refresh_token: string, open_id: string, scope: string, expires_at: string, refresh_expires_at: string}
     */
    public function tokens(Response $response): array
    {
        $json = $response->json() ?? [];

        if (! $response->successful() || blank($json['access_token'] ?? null)) {
            throw ValidationException::withMessages(['tiktok' => (string) ($json['error_description'] ?? __('TikTok sign-in failed. Please try again.'))]);
        }

        return [
            'access_token' => (string) $json['access_token'],
            'refresh_token' => (string) ($json['refresh_token'] ?? ''),
            'open_id' => (string) ($json['open_id'] ?? ''),
            'scope' => (string) ($json['scope'] ?? ''),
            'expires_at' => now()->addSeconds((int) ($json['expires_in'] ?? 86400))->toIso8601String(),
            'refresh_expires_at' => now()->addSeconds((int) ($json['refresh_expires_in'] ?? 31536000))->toIso8601String(),
        ];
    }
}
