<?php

namespace App\Modules\TikTok\Services;

use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Carbon\CarbonImmutable;

/**
 * Keeps TikTok access tokens fresh. TikTok may return a new refresh token on every refresh; the newest one must be stored.
 */
class TikTokTokenService
{
    protected const REFRESH_WINDOW_HOURS = 2;

    public function __construct(protected TikTokClient $client, protected TikTokConnectService $connect) {}

    public function needsRefresh(ChannelAccount $account): bool
    {
        $expiresAt = $account->credential('expires_at');

        return blank($expiresAt) || CarbonImmutable::parse($expiresAt)->subHours(self::REFRESH_WINDOW_HOURS)->isPast();
    }

    /**
     * A valid access token, refreshing first when it is about to expire. Null when the account must reconnect.
     */
    public function accessToken(ChannelAccount $account): ?string
    {
        if ($this->needsRefresh($account) && ! $this->refresh($account)) {
            return null;
        }

        return (string) $account->fresh()->credential('access_token') ?: null;
    }

    public function refresh(ChannelAccount $account): bool
    {
        $refreshToken = (string) $account->credential('refresh_token');

        if ($refreshToken === '') {
            return $this->markReconnectNeeded($account, 'TikTok refresh token is missing.');
        }

        try {
            $response = $this->client->refresh($refreshToken);
        } catch (\Throwable $exception) {
            // A network failure is temporary: keep the account connected and retry on the next run.
            report($exception);

            return false;
        }

        if ($response->serverError()) {
            return false;
        }

        if ($response->failed() || blank($response->json('access_token'))) {
            return $this->markReconnectNeeded($account, (string) ($response->json('error_description') ?: 'TikTok rejected the refresh token.'));
        }

        $tokens = $this->connect->tokens($response);
        $tokens['refresh_token'] = $tokens['refresh_token'] ?: $refreshToken;
        $tokens['open_id'] = $tokens['open_id'] ?: (string) $account->credential('open_id');

        $settings = $account->settings ?? [];
        unset($settings['last_error']);

        $account->update([
            'status' => ChannelAccountStatus::Connected->value,
            'credentials' => $tokens,
            'settings' => $settings,
            'last_synced_at' => now(),
        ]);

        return true;
    }

    /**
     * Refreshes every connected TikTok account whose token expires soon.
     */
    public function refreshDue(): int
    {
        return ChannelAccount::query()
            ->where('provider', 'tiktok')
            ->where('status', ChannelAccountStatus::Connected->value)
            ->get()
            ->filter(fn (ChannelAccount $account): bool => $this->needsRefresh($account) && $this->refresh($account))
            ->count();
    }

    protected function markReconnectNeeded(ChannelAccount $account, string $reason): false
    {
        $account->update([
            'status' => ChannelAccountStatus::Error->value,
            'settings' => array_merge($account->settings ?? [], ['last_error' => $reason.' Reconnect TikTok.']),
        ]);

        return false;
    }
}
