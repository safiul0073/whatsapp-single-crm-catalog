<?php

namespace App\Modules\MetaSocial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaWebhookController extends Controller
{
    public function verify(Request $request, string $token, ChannelManager $channels): Response
    {
        $account = $this->channelForToken($token);

        abort_unless($account && $channels->verifyWebhook($request, $account), 403);

        return response((string) ($request->query('hub_challenge') ?? $request->query('hub.challenge') ?? ''), 200);
    }

    public function receive(Request $request, string $token, ChannelManager $channels): Response
    {
        $accounts = ChannelAccount::query()
            ->whereIn('provider', ['messenger', 'instagram'])
            ->where('webhook_verify_token', $token)
            ->get();

        abort_if($accounts->isEmpty(), 404);

        $account = $this->channelForPayload($request->all(), $accounts) ?? $accounts->first();
        $channels->handleWebhook($request, $account);

        return response('', 200);
    }

    protected function channelForToken(string $token): ?ChannelAccount
    {
        return ChannelAccount::query()
            ->whereIn('provider', ['messenger', 'instagram'])
            ->where('webhook_verify_token', $token)
            ->first();
    }

    protected function channelForPayload(array $payload, $accounts): ?ChannelAccount
    {
        $entryId = data_get($payload, 'entry.0.id');

        if (! $entryId) {
            return null;
        }

        return $accounts->first(fn (ChannelAccount $account): bool => in_array($entryId, [
            $account->provider_account_id,
            $account->provider_phone_id,
            $account->provider_display_id,
        ], true));
    }
}
