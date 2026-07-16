<?php

namespace App\Modules\MarketingChannels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Jobs\ProcessChannelWebhookJob;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\ChannelManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WebhookController extends Controller
{
    public function verify(Request $request, ChannelManager $channels, string $provider, ?string $webhookCode = null): Response
    {
        $account = $this->resolveAccountForVerify($request, $provider, $webhookCode);

        abort_unless($account, 404);
        abort_unless($this->verifyWithDriver($channels, $request, $account), 403);

        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge') ?? '';

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, ChannelManager $channels, string $provider, ?string $webhookCode = null): Response
    {

        $account = $this->resolveAccountForReceive($request, $provider, $webhookCode);

        if (! $account) {
            Log::warning('Channel webhook could not resolve account.', [
                'provider' => $provider,
                'webhook_code' => $webhookCode,
                'payload_keys' => array_keys($request->all()),
            ]);

            return response('Channel account not found.', 404);
        }

        try {
            $payload = $request->all();
            $hash = sha1(json_encode($payload).$account->id);

            $event = ChannelWebhookEvent::query()->firstOrCreate(
                ['payload_hash' => $hash],
                [
                    'channel_account_id' => $account->id,
                    'workspace_id' => $account->workspace_id,
                    'provider' => $provider,
                    'event_type' => $this->detectEventType($payload),
                    'payload' => $payload,
                    'headers' => $request->headers->all(),
                    'status' => ChannelWebhookEventStatus::Pending->value,
                ]
            );

            if (! $event->processed_at && ! $event->failed_at) {
                if ($provider === 'telegram') {
                    ProcessChannelWebhookJob::dispatchSync($event->id);
                } else {
                    ProcessChannelWebhookJob::dispatch($event->id);
                }
            }
        } catch (InvalidArgumentException $exception) {
            Log::warning('Channel webhook driver is not registered.', [
                'provider' => $provider,
                'channel_account_id' => $account->id,
                'message' => $exception->getMessage(),
            ]);

            return response('Channel driver not configured.', 422);
        }

        return response('', 200);
    }

    protected function resolveAccountForVerify(Request $request, string $provider, ?string $webhookCode = null): ?ChannelAccount
    {

        if (filled($webhookCode)) {
            return ChannelAccount::query()
                ->where('provider', $provider)
                ->where('webhook_code', $webhookCode)
                ->latest()
                ->first();
        }

        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $account = ChannelAccount::query()
            ->where('provider', $provider)
            ->where(function ($query) use ($token): void {
                $query->where('webhook_verify_token', $token)
                    ->orWhere('provider_account_id', $token);
            })
            ->latest()
            ->first();

        if ($account) {
            return $account;
        }

        return ChannelAccount::query()
            ->where('provider', $provider)
            ->whereNotNull('connected_at')
            ->latest()
            ->first();
    }

    protected function verifyWithDriver(ChannelManager $channels, Request $request, ChannelAccount $account): bool
    {
        try {
            return $channels->verifyWebhook($request, $account);
        } catch (InvalidArgumentException) {
            $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
            $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
            $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

            return $mode === 'subscribe'
                && filled($challenge)
                && filled($token)
                && hash_equals((string) $account->webhook_verify_token, (string) $token);
        }
    }

    protected function resolveAccountForReceive(Request $request, string $provider, ?string $webhookCode = null): ?ChannelAccount
    {
        $query = ChannelAccount::query()
            ->where('provider', $provider)
            ->whereNotNull('connected_at');

        if (filled($webhookCode)) {
            return (clone $query)->where('webhook_code', $webhookCode)->latest()->first();
        }

        if ($provider === 'whatsapp') {
            $phoneNumberId = data_get($request->all(), 'entry.0.changes.0.value.metadata.phone_number_id');

            if (filled($phoneNumberId)) {
                return (clone $query)->where('provider_phone_id', $phoneNumberId)->latest()->first();
            }
        }

        if ($provider === 'telegram') {
            $secretToken = $request->headers->get('X-Telegram-Bot-Api-Secret-Token');

            if (filled($secretToken)) {
                return (clone $query)->where('webhook_verify_token', $secretToken)->latest()->first();
            }

            Log::warning('Telegram webhook missing secret token header.', [
                'provider' => $provider,
                'payload_keys' => array_keys($request->all()),
            ]);

            $accounts = (clone $query)->latest()->limit(2)->get();

            return $accounts->count() === 1 ? $accounts->first() : null;
        }

        $providerAccountId = data_get($request->all(), 'entry.0.id')
            ?? data_get($request->all(), 'account_id')
            ?? data_get($request->all(), 'provider_account_id');

        if (filled($providerAccountId)) {
            return (clone $query)->where('provider_account_id', $providerAccountId)->latest()->first();
        }

        return (clone $query)->latest()->first();
    }

    protected function detectEventType(array $payload): string
    {
        if (filled(data_get($payload, 'entry.0.changes.0.value.messages')) || filled(data_get($payload, 'message'))) {
            return 'message.received';
        }

        if (filled(data_get($payload, 'entry.0.changes.0.value.statuses')) || filled(data_get($payload, 'delivery.mids'))) {
            return 'message.status';
        }

        return 'unknown';
    }
}
