<?php

namespace App\Modules\Threads\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ThreadsProvider implements MarketingChannelDriver
{
    public function __construct(protected ThreadsClient $client) {}

    public function provider(): string
    {
        return 'threads';
    }

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
    {
        $token = (string) $account->credential('access_token');
        $threadsUserId = (string) $account->provider_account_id;
        $body = trim((string) ($payload['body'] ?? $payload['text'] ?? ''));
        $replyToId = $payload['reply_to_id'] ?? null;

        if (blank($token)) {
            return ['ok' => false, 'provider' => $this->provider(), 'status' => 'failed', 'error' => 'Threads access token is missing.'];
        }

        if (blank($threadsUserId)) {
            return ['ok' => false, 'provider' => $this->provider(), 'status' => 'failed', 'error' => 'Threads Account ID is missing.'];
        }

        if (blank($body)) {
            return ['ok' => false, 'provider' => $this->provider(), 'status' => 'failed', 'error' => 'Post text is required.'];
        }

        try {
            $creation = $this->client->createTextPost($threadsUserId, $token, $body, filled($replyToId) ? (string) $replyToId : null);
            $creationJson = $creation->json() ?? [];
            $creationId = (string) data_get($creationJson, 'id');

            if (! $creation->successful() || blank($creationId)) {
                return [
                    'ok' => false,
                    'provider' => $this->provider(),
                    'status' => 'failed',
                    'response' => $creationJson,
                    'error' => data_get($creationJson, 'error.message', 'Threads post creation failed.'),
                ];
            }

            $published = $this->client->publishPost($threadsUserId, $token, $creationId);
            $publishedJson = $published->json() ?? [];

            return [
                'ok' => $published->successful(),
                'provider' => $this->provider(),
                'provider_message_id' => data_get($publishedJson, 'id'),
                'status' => $published->successful() ? MessageStatus::Sent->value : MessageStatus::Failed->value,
                'response' => $publishedJson,
                'error' => data_get($publishedJson, 'error.message'),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function verifyWebhook(Request $request, ChannelAccount $account): bool
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        return $mode === 'subscribe'
            && filled($challenge)
            && filled($token)
            && hash_equals((string) $account->webhook_verify_token, (string) $token);
    }

    public function handleWebhook(Request $request, ChannelAccount $account): array
    {
        return $this->processWebhook($account, $request->all());
    }

    public function syncTemplates(ChannelAccount $account): array
    {
        return ['ok' => true, 'synced' => 0, 'provider' => $this->provider()];
    }

    public function getHealthStatus(ChannelAccount $account): array
    {
        return [
            'provider' => $this->provider(),
            'status' => $account->status,
            'connected' => $account->status === ChannelAccountStatus::Connected,
            'has_credentials' => filled($account->credential('access_token')),
        ];
    }

    public function testConnection(ChannelAccount $account): array
    {
        $token = (string) $account->credential('access_token');

        if (blank($token)) {
            return ['ok' => false, 'provider' => $this->provider(), 'error' => 'Threads access token is missing.'];
        }

        if (blank($account->provider_account_id)) {
            return ['ok' => false, 'provider' => $this->provider(), 'error' => 'Threads Account ID is missing.'];
        }

        try {
            $response = $this->client->account((string) $account->provider_account_id, $token);
            $json = $response->json() ?? [];

            return [
                'ok' => $response->successful() && (string) data_get($json, 'id') === (string) $account->provider_account_id,
                'provider' => $this->provider(),
                'account_id' => data_get($json, 'id'),
                'display_name' => data_get($json, 'username', data_get($json, 'name')),
                'response' => $json,
                'error' => data_get($json, 'error.message'),
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'provider' => $this->provider(), 'error' => $exception->getMessage()];
        }
    }

    public function processWebhook(ChannelAccount $account, array $payload): array
    {
        $events = [];

        foreach ($this->changes($payload) as $change) {
            $value = (array) ($change['value'] ?? []);
            $text = data_get($value, 'text') ?? data_get($value, 'message');
            $senderId = data_get($value, 'from.id') ?? data_get($value, 'sender.id') ?? data_get($value, 'user_id');
            $username = data_get($value, 'from.username') ?? data_get($value, 'sender.username') ?? data_get($value, 'username');
            $senderName = data_get($value, 'from.name') ?? data_get($value, 'sender.name') ?? data_get($value, 'name');
            $messageId = data_get($value, 'id') ?? data_get($value, 'comment_id') ?? data_get($value, 'reply_id');
            $status = data_get($value, 'status');

            if (filled($text) && filled($senderId)) {
                $events[] = [
                    'type' => 'message',
                    'provider_message_id' => $messageId,
                    'provider_contact_id' => $senderId,
                    'username' => $username,
                    'name' => $senderName,
                    'body' => $text,
                    'payload' => $value,
                ];
            }

            if (filled($messageId) && filled($status)) {
                $events[] = [
                    'type' => 'status',
                    'provider_message_id' => $messageId,
                    'status' => $status,
                    'payload' => $value,
                ];
            }
        }

        return ['ok' => true, 'events' => $events];
    }

    public function getCapabilities(): array
    {
        return ['Publishing', 'Inbox', 'Webhooks'];
    }

    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
    {
        throw ValidationException::withMessages([
            'channel_account_id' => 'Threads does not support recipient-based campaigns. Use channel publishing instead.',
        ]);
    }

    protected function changes(array $payload): array
    {
        $changes = Arr::flatten(data_get($payload, 'entry.*.changes', []), 1);

        if ($changes !== []) {
            return $changes;
        }

        return isset($payload['value']) ? [['value' => $payload['value']]] : [];
    }
}
