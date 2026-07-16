<?php

namespace App\Modules\WhatsAppCloud\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\WhatsAppCloud\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function verify(Request $request, string $account, ChannelManager $channels): Response
    {
        $channel = $this->channelForAccount($account);

        if ($channel) {
            abort_unless($channels->verifyWebhook($request, $channel), 403);
        } else {
            $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
            $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
            abort_unless($mode === 'subscribe' && filled($token), 403);
        }

        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        return response((string) $challenge, 200);
    }

    public function receive(Request $request, string $account, ChannelManager $channels): Response
    {
        $channel = $this->channelForAccount($account);

        if ($channel) {
            $channels->handleWebhook($request, $channel);
        } else {
            ProcessWhatsAppWebhookJob::dispatch($account, $request->all(), $request->headers->all());
        }

        return response('', 200);
    }

    protected function channelForAccount(string $account): ?ChannelAccount
    {
        return ChannelAccount::query()
            ->where('provider', 'whatsapp')
            ->where(function ($query) use ($account): void {
                $query->where('provider_account_id', $account)
                    ->orWhere('provider_phone_id', $account);
            })
            ->latest()
            ->first();
    }
}
