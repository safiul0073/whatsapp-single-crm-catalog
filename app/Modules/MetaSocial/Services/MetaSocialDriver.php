<?php

namespace App\Modules\MetaSocial\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelWebhookIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class MetaSocialDriver implements MarketingChannelDriver
{
    public function __construct(
        protected MetaSocialClient $client,
        protected string $provider,
    ) {}

    public function provider(): string
    {
        return $this->provider;
    }

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
    {
        $recipientId = (string) ($recipient['id'] ?? $recipient['provider_contact_id'] ?? $recipient['to'] ?? '');
        $token = (string) $account->credential('access_token');
        $body = (string) ($payload['body'] ?? $payload['text'] ?? '');

        if ($this->provider === 'instagram' && filled($payload['reply_to_comment_id'] ?? null)) {
            $response = $this->client->replyToInstagramComment((string) $payload['reply_to_comment_id'], $token, $body);
            $json = $response->json() ?? [];

            return [
                'ok' => $response->successful(),
                'provider' => $this->provider(),
                'provider_message_id' => data_get($json, 'id'),
                'status' => $response->successful() ? MessageStatus::Sent->value : MessageStatus::Failed->value,
                'response' => $json,
            ];
        }

        $metaPayload = $payload['meta_payload'] ?? $this->messagePayload($recipientId, $body, $payload);

        if (array_key_exists('notification_messages_token', (array) ($metaPayload['recipient'] ?? [])) && blank($metaPayload['recipient']['notification_messages_token'])) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'status' => MessageStatus::Failed->value,
                'error_code' => 'messenger_optin_missing',
                'error' => 'This customer is not subscribed to Messenger offers (or stopped them).',
                'response' => [],
            ];
        }

        if (($payload['human_agent'] ?? false) === true) {
            $metaPayload['messaging_type'] = 'MESSAGE_TAG';
            $metaPayload['tag'] = 'HUMAN_AGENT';
        }

        $response = $this->provider === 'instagram'
            ? $this->client->sendInstagramMessage((string) $account->provider_account_id, $token, $metaPayload)
            : $this->client->sendMessengerMessage((string) $account->provider_account_id, $token, $metaPayload);
        $json = $response->json() ?? [];

        return [
            'ok' => $response->successful(),
            'provider' => $this->provider(),
            'provider_message_id' => data_get($json, 'message_id'),
            'status' => $response->successful() ? MessageStatus::Sent->value : MessageStatus::Failed->value,
            'response' => $json,
        ];
    }

    protected function messagePayload(string $recipientId, string $body, array $payload): array
    {
        if (in_array(($payload['type'] ?? null), ['image', 'video', 'audio', 'document'], true)) {
            return [
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => $this->metaAttachmentType((string) $payload['type']),
                        'payload' => [
                            'url' => (string) ($payload['url'] ?? ''),
                            'is_reusable' => true,
                        ],
                    ],
                ],
            ];
        }

        return [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $body],
        ];
    }

    protected function metaAttachmentType(string $type): string
    {
        return $type === 'document' ? 'file' : $type;
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

    /**
     * Meta webhooks go through the shared pipeline (ProcessChannelWebhookJob) so inbox, push alerts,
     * auto-replies, the AI bot, automations and campaign tracking all run.
     */
    public function handleWebhook(Request $request, ChannelAccount $account): array
    {
        $event = app(ChannelWebhookIntake::class)->accept($account, $this->provider(), $request->all(), $request->headers->all());

        return ['ok' => true, 'event_id' => $event->id, 'type' => $event->event_type];
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

    protected function messagingEvents(array $payload): array
    {
        return collect(Arr::flatten(data_get($payload, 'entry.*.messaging', []), 1))
            ->filter(fn (mixed $event): bool => is_array($event)
                && data_get($event, 'message.is_echo') !== true
                && (filled(data_get($event, 'message.text')) || filled(data_get($event, 'message.attachments'))))
            ->values()
            ->all();
    }

    protected function deliveryEvents(array $payload): array
    {
        return collect(Arr::flatten(data_get($payload, 'entry.*.messaging', []), 1))
            ->filter(fn (mixed $event): bool => is_array($event) && (filled(data_get($event, 'delivery.mids')) || filled(data_get($event, 'read.watermark'))))
            ->values()
            ->all();
    }

    /**
     * Marketing-messages opt-ins (and their STOP / RESUME changes) arrive as messaging items with "optin".
     *
     * @return array<int, array<string, mixed>>
     */
    protected function optinEvents(array $payload): array
    {
        return collect(Arr::flatten(data_get($payload, 'entry.*.messaging', []), 1))
            ->filter(fn (mixed $event): bool => is_array($event) && filled(data_get($event, 'optin')) && filled(data_get($event, 'sender.id')))
            ->values()
            ->all();
    }

    /**
     * Instagram comment webhooks arrive as entry.*.changes with field "comments".
     *
     * @return array<int, array<string, mixed>>
     */
    protected function commentEvents(array $payload): array
    {
        if ($this->provider() !== 'instagram') {
            return [];
        }

        return collect(Arr::flatten(data_get($payload, 'entry.*.changes', []), 1))
            ->filter(fn (mixed $change): bool => is_array($change)
                && data_get($change, 'field') === 'comments'
                && filled(data_get($change, 'value.id'))
                && filled(data_get($change, 'value.from.id'))
                && filled(data_get($change, 'value.text')))
            ->pluck('value')
            ->values()
            ->all();
    }

    public function persistInboundComment(ChannelAccount $account, array $comment): ?Message
    {
        $senderId = (string) data_get($comment, 'from.id');

        if ($senderId === '' || $senderId === (string) $account->provider_account_id) {
            return null;
        }

        $identity = ContactProviderIdentity::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('provider', $this->provider())
            ->where('provider_contact_id', $senderId)
            ->first();

        $contact = $identity?->contact ?: Contact::query()->create([
            'workspace_id' => $account->workspace_id,
            'name' => (string) (data_get($comment, 'from.username') ?: $this->providerLabel().' '.$senderId),
            'phone' => null,
            'opt_in_status' => ContactOptInStatus::Subscribed->value,
            'opted_in_at' => now(),
        ]);

        ContactProviderIdentity::query()->updateOrCreate(
            [
                'workspace_id' => $account->workspace_id,
                'provider' => $this->provider(),
                'provider_contact_id' => $senderId,
            ],
            [
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
                'identity_type' => 'instagram_user_id',
                'metadata' => array_filter(['username' => data_get($comment, 'from.username')]),
            ]
        );

        $conversation = Conversation::query()->firstOrCreate(
            [
                'workspace_id' => $account->workspace_id,
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
            ],
            [
                'provider' => $this->provider(),
                'provider_conversation_id' => $senderId,
                'status' => ConversationStatus::Open->value,
                'labels' => [],
            ]
        );

        $message = Message::query()->updateOrCreate(
            ['provider_message_id' => (string) data_get($comment, 'id')],
            [
                'workspace_id' => $account->workspace_id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
                'provider' => $this->provider(),
                'direction' => 'inbound',
                'type' => 'comment',
                'body' => data_get($comment, 'text'),
                'payload' => ['comment' => $comment],
                'status' => MessageStatus::Received->value,
            ]
        );

        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    protected function providerLabel(): string
    {
        return $this->provider() === 'instagram' ? 'Instagram' : 'Messenger';
    }

    public function testConnection(ChannelAccount $account): array
    {
        $token = (string) $account->credential('access_token');

        if (blank($token)) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'error' => 'Access token is missing.',
            ];
        }

        if (blank($account->provider_account_id)) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'error' => $this->provider() === 'instagram' ? 'Instagram Account ID is missing.' : 'Facebook Page ID is missing.',
            ];
        }

        try {
            $response = $this->client->account(
                (string) $account->provider_account_id,
                $token,
                $this->provider() === 'instagram' ? ['id', 'username', 'name'] : ['id', 'name']
            );
            $json = $response->json() ?? [];

            return [
                'ok' => $response->successful(),
                'provider' => $this->provider(),
                'account_id' => data_get($json, 'id'),
                'display_name' => data_get($json, $this->provider() === 'instagram' ? 'username' : 'name'),
                'response' => $json,
                'error' => data_get($json, 'error.message'),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function processWebhook(ChannelAccount $account, array $payload): array
    {
        $events = [];

        foreach ($this->messagingEvents($payload) as $event) {
            $events[] = [
                'type' => 'message',
                'provider_message_id' => data_get($event, 'message.mid'),
                'provider_contact_id' => data_get($event, 'sender.id'),
                'body' => data_get($event, 'message.text'),
                'payload' => array_merge($event, ['type' => filled(data_get($event, 'message.attachments')) ? 'media' : 'text']),
            ];
        }

        foreach ($this->commentEvents($payload) as $comment) {
            $events[] = ['type' => 'comment', 'payload' => $comment];
        }

        foreach ($this->optinEvents($payload) as $event) {
            $events[] = [
                'type' => 'optin',
                'provider_contact_id' => data_get($event, 'sender.id'),
                'payload' => $event,
            ];
        }

        foreach ($this->deliveryEvents($payload) as $event) {
            foreach ((array) data_get($event, 'delivery.mids', []) as $messageId) {
                $events[] = [
                    'type' => 'status',
                    'provider_message_id' => $messageId,
                    'status' => 'delivered',
                    'payload' => $event,
                ];
            }

            if (filled(data_get($event, 'read.watermark'))) {
                $events[] = [
                    'type' => 'status',
                    'provider_message_id' => null,
                    'status' => 'read',
                    'payload' => $event,
                ];
            }
        }

        return ['ok' => true, 'events' => $events];
    }

    public function getCapabilities(): array
    {
        return ['Inbox', 'Automation', 'Webhooks'];
    }

    /**
     * Messenger broadcasts go only to customers who opted in to marketing messages (one token each),
     * so a custom-text broadcast is allowed there. Instagram has no opt-in broadcasts.
     */
    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
    {
        if ($this->provider() === 'messenger' && $campaign->type === 'broadcast') {
            if ($campaign->message_type !== 'custom' || blank($campaign->message_body)) {
                throw ValidationException::withMessages([
                    'message_body' => 'Messenger offer broadcasts need a message. Templates are not used on Messenger.',
                ]);
            }

            return;
        }

        if ($campaign->type === 'broadcast') {
            throw ValidationException::withMessages([
                'type' => 'Unrestricted bulk campaigns are not allowed for '.ucfirst($this->provider()).'. Use follow-up or automation campaigns only.',
            ]);
        }
    }
}
