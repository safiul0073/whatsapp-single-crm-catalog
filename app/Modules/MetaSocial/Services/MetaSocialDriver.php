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
use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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

        $metaPayload = $payload['meta_payload'] ?? $this->messagePayload($recipientId, $body, $payload);

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

    public function handleWebhook(Request $request, ChannelAccount $account): array
    {
        $payload = $request->all();
        $eventType = $this->eventType($payload);
        $eventId = $this->eventId($payload);

        $event = ChannelWebhookEvent::query()->firstOrCreate(
            ['provider' => $this->provider(), 'provider_event_id' => $eventId],
            [
                'channel_account_id' => $account->id,
                'workspace_id' => $account->workspace_id,
                'event_type' => $eventType,
                'payload' => $payload,
                'headers' => $request->headers->all(),
                'status' => ChannelWebhookEventStatus::Pending->value,
            ]
        );

        if ($event->processed_at) {
            return ['ok' => true, 'duplicate' => true, 'event_id' => $event->id];
        }

        try {
            foreach ($this->messagingEvents($payload) as $message) {
                $this->persistInboundMessage($account, $message);
            }

            foreach ($this->deliveryEvents($payload) as $delivery) {
                $this->persistDeliveryStatus($account, $delivery);
            }

            $event->update(['status' => ChannelWebhookEventStatus::Processed->value, 'processed_at' => now()]);

            return ['ok' => true, 'event_id' => $event->id, 'type' => $eventType];
        } catch (\Throwable $exception) {
            $event->update(['status' => ChannelWebhookEventStatus::Failed->value, 'error' => $exception->getMessage()]);

            throw $exception;
        }
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
            ->filter(fn (array $event): bool => filled(data_get($event, 'message.text')) || filled(data_get($event, 'message.attachments')))
            ->values()
            ->all();
    }

    protected function deliveryEvents(array $payload): array
    {
        return collect(Arr::flatten(data_get($payload, 'entry.*.messaging', []), 1))
            ->filter(fn (array $event): bool => filled(data_get($event, 'delivery.mids')) || filled(data_get($event, 'read.watermark')))
            ->values()
            ->all();
    }

    protected function eventType(array $payload): string
    {
        if ($this->messagingEvents($payload) !== []) {
            return 'message.received';
        }

        if ($this->deliveryEvents($payload) !== []) {
            return 'message.status';
        }

        return data_get($payload, 'object', $this->provider().'.event');
    }

    protected function eventId(array $payload): string
    {
        return data_get($payload, 'entry.0.id', 'event').'-'.data_get($payload, 'entry.0.time', time()).'-'.sha1(json_encode($payload));
    }

    protected function persistInboundMessage(ChannelAccount $account, array $event): void
    {
        $senderId = (string) data_get($event, 'sender.id');
        $messageId = (string) (data_get($event, 'message.mid') ?: Str::uuid());

        if ($senderId === '') {
            return;
        }

        $identity = ContactProviderIdentity::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('provider', $this->provider())
            ->where('provider_contact_id', $senderId)
            ->first();

        $contact = $identity?->contact ?: Contact::query()->create([
            'workspace_id' => $account->workspace_id,
            'name' => $this->providerLabel().' '.$senderId,
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
                'identity_type' => $this->provider() === 'instagram' ? 'instagram_user_id' : 'psid',
                'metadata' => ['recipient_id' => data_get($event, 'recipient.id')],
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

        Message::query()->updateOrCreate(
            ['provider_message_id' => $messageId],
            [
                'workspace_id' => $account->workspace_id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
                'provider' => $this->provider(),
                'direction' => 'inbound',
                'type' => filled(data_get($event, 'message.attachments')) ? 'media' : 'text',
                'body' => data_get($event, 'message.text'),
                'payload' => $event,
                'status' => MessageStatus::Received->value,
            ]
        );

        $conversation->update(['last_message_at' => now()]);
    }

    protected function persistDeliveryStatus(ChannelAccount $account, array $event): void
    {
        foreach ((array) data_get($event, 'delivery.mids', []) as $messageId) {
            Message::query()
                ->where('workspace_id', $account->workspace_id)
                ->where('provider', $this->provider())
                ->where('provider_message_id', $messageId)
                ->update(['status' => MessageStatus::Delivered->value]);
        }
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

    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
    {
        if ($campaign->type === 'broadcast') {
            throw ValidationException::withMessages([
                'type' => 'Unrestricted bulk campaigns are not allowed for '.ucfirst($this->provider()).'. Use follow-up or automation campaigns only.',
            ]);
        }
    }
}
