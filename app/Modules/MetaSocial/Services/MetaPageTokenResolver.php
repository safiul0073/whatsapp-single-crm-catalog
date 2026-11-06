<?php

namespace App\Modules\MetaSocial\Services;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MetaSocial\Models\MetaBusinessConnection;
use Illuminate\Validation\ValidationException;

/**
 * Finds a Facebook Page access token for a workspace: the connected Messenger channel's token first,
 * otherwise the Page token Meta returns for the business login (me/accounts).
 */
class MetaPageTokenResolver
{
    public function __construct(protected MetaSocialClient $client) {}

    public function forPage(int $workspaceId, string $pageId): string
    {
        $channelToken = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'messenger')
            ->where('provider_account_id', $pageId)
            ->latest('id')
            ->first()?->credential('access_token');

        if (filled($channelToken)) {
            return (string) $channelToken;
        }

        $userToken = MetaBusinessConnection::query()->where('workspace_id', $workspaceId)->first()?->accessToken();
        $page = filled($userToken)
            ? collect((array) data_get($this->client->pageAccounts((string) $userToken)->json(), 'data', []))->firstWhere('id', $pageId)
            : null;

        if (blank($page['access_token'] ?? null)) {
            throw ValidationException::withMessages(['page_id' => __('Meta did not share this Page\'s access. Reconnect Meta and include the Page.')]);
        }

        return (string) $page['access_token'];
    }

    /**
     * Facebook Pages this workspace can use: from the Meta Business connection and connected Messenger channels.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function pages(int $workspaceId): array
    {
        $fromBusiness = collect(MetaBusinessConnection::query()->where('workspace_id', $workspaceId)->first()?->assetsOfType('messenger') ?? [])
            ->map(fn (array $page): array => ['id' => (string) $page['id'], 'name' => (string) ($page['name'] ?? $page['id'])]);
        $fromChannels = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'messenger')
            ->whereNotNull('provider_account_id')
            ->get(['provider_account_id', 'name', 'provider_display_id'])
            ->map(fn (ChannelAccount $account): array => ['id' => (string) $account->provider_account_id, 'name' => (string) ($account->provider_display_id ?: $account->name)]);

        return $fromBusiness->concat($fromChannels)->unique('id')->values()->all();
    }

    /**
     * Instagram professional accounts this workspace can use.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function instagramAccounts(int $workspaceId): array
    {
        $fromBusiness = collect(MetaBusinessConnection::query()->where('workspace_id', $workspaceId)->first()?->assetsOfType('instagram') ?? [])
            ->map(fn (array $account): array => ['id' => (string) $account['id'], 'name' => filled($account['username'] ?? null) ? '@'.$account['username'] : (string) ($account['name'] ?? $account['id']), 'page_id' => null]);
        $fromChannels = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'instagram')
            ->whereNotNull('provider_account_id')
            ->get(['provider_account_id', 'name', 'provider_display_id', 'settings'])
            ->map(fn (ChannelAccount $account): array => ['id' => (string) $account->provider_account_id, 'name' => (string) ($account->provider_display_id ?: $account->name), 'page_id' => data_get($account->settings, 'page_id')]);

        return $fromBusiness->concat($fromChannels)->unique('id')->values()->all();
    }
}
