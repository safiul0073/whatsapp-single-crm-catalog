<?php

namespace App\Modules\TikTok\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * TikTok has no public messaging API for ordinary businesses, so this driver only connects and tests the account.
 */
class TikTokDriver implements MarketingChannelDriver
{
    public function __construct(protected TikTokClient $client, protected TikTokTokenService $tokens) {}

    public function provider(): string
    {
        return 'tiktok';
    }

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
    {
        return [
            'ok' => false,
            'provider' => $this->provider(),
            'status' => 'failed',
            'error' => 'TikTok does not support sending messages from this platform.',
        ];
    }

    public function verifyWebhook(Request $request, ChannelAccount $account): bool
    {
        return false;
    }

    public function handleWebhook(Request $request, ChannelAccount $account): array
    {
        return ['ok' => true, 'ignored' => true, 'provider' => $this->provider()];
    }

    public function syncTemplates(ChannelAccount $account): array
    {
        $account->update(['last_synced_at' => now()]);

        return ['ok' => true, 'synced' => 0, 'provider' => $this->provider()];
    }

    public function getHealthStatus(ChannelAccount $account): array
    {
        return [
            'provider' => $this->provider(),
            'status' => $account->status,
            'connected' => $account->status === ChannelAccountStatus::Connected,
            'last_synced_at' => $account->last_synced_at,
            'has_credentials' => filled($account->credential('access_token')),
        ];
    }

    public function testConnection(ChannelAccount $account): array
    {
        $token = $this->tokens->accessToken($account);

        if (! $token) {
            return ['ok' => false, 'provider' => $this->provider(), 'error' => 'TikTok access has expired. Reconnect TikTok.'];
        }

        try {
            $response = $this->client->userInfo($token);
        } catch (\Throwable $exception) {
            return ['ok' => false, 'provider' => $this->provider(), 'error' => $exception->getMessage()];
        }

        return [
            'ok' => $response->successful() && in_array($response->json('error.code'), [null, 'ok'], true),
            'provider' => $this->provider(),
            'account_id' => $response->json('data.user.open_id'),
            'display_name' => $response->json('data.user.display_name'),
            'error' => $response->json('error.message') ?: null,
        ];
    }

    public function processWebhook(ChannelAccount $account, array $payload): array
    {
        return ['ok' => true, 'events' => []];
    }

    public function getCapabilities(): array
    {
        return ['Login'];
    }

    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
    {
        throw ValidationException::withMessages(['type' => 'Campaigns are not available for TikTok.']);
    }
}
