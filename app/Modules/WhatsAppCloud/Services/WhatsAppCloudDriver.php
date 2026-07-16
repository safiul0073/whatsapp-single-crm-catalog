<?php

namespace App\Modules\WhatsAppCloud\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Commerce\Services\OrderIntakeService;
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
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MessageTemplates\Models\MessageTemplateSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsAppCloudDriver implements MarketingChannelDriver
{
    public function __construct(protected WhatsAppCloudClient $client) {}

    public function provider(): string
    {
        return 'whatsapp';
    }

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
    {
        $phone = preg_replace('/\D+/', '', (string) ($recipient['phone'] ?? $recipient['to'] ?? ''));
        $token = (string) $account->credential('access_token');

        $metaPayload = $payload['meta_payload'] ?? $this->messagePayload($phone, $payload);
        try {
            $response = $this->client->sendMessage((string) $account->provider_phone_id, $token, $metaPayload);
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'provider_message_id' => null,
                'status' => MessageStatus::Failed->value,
                'error_code' => 'connection_error',
                'error' => $exception->getMessage(),
                'response' => ['error' => ['message' => $exception->getMessage()]],
            ];
        }

        $json = $response->json() ?? [];

        return [
            'ok' => $response->successful(),
            'provider' => $this->provider(),
            'provider_message_id' => data_get($json, 'messages.0.id'),
            'status' => $response->successful() ? MessageStatus::Sent->value : MessageStatus::Failed->value,
            'error_code' => data_get($json, 'error.code'),
            'error' => data_get($json, 'error.message'),
            'response' => $json,
        ];
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
            foreach ($this->messages($payload) as $message) {
                $this->persistInboundMessage($account, $message);
            }

            foreach ($this->statuses($payload) as $status) {
                $this->persistStatus($account, $status);
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
        $response = $this->client->syncTemplates((string) $account->provider_account_id, (string) $account->credential('access_token'));
        $templates = $response->json('data') ?? [];
        $synced = 0;

        foreach ($templates as $template) {
            $localTemplate = MessageTemplate::query()->updateOrCreate(
                [
                    'workspace_id' => $account->workspace_id,
                    'name' => $template['name'] ?? 'template_'.$synced,
                    'language' => $template['language'] ?? 'en_US',
                ],
                [
                    'provider' => $this->provider(),
                    'category' => strtolower($template['category'] ?? 'marketing'),
                    'components' => $template['components'] ?? [],
                    'submission_payload' => $template,
                ]
            );

            MessageTemplateSubmission::query()->updateOrCreate(
                [
                    'workspace_id' => $account->workspace_id,
                    'message_template_id' => $localTemplate->id,
                    'provider_account_id' => (string) $account->provider_account_id,
                ],
                [
                    'channel_account_id' => $account->id,
                    'provider' => $this->provider(),
                    'whatsapp_template_id' => $template['id'] ?? null,
                    'status' => $this->templateStatus($template['status'] ?? null),
                    'submission_payload' => $template,
                    'meta_response' => $template,
                    'synced_at' => now(),
                ]
            );

            $this->refreshTemplateSummaryStatus($localTemplate);
            $synced++;
        }

        $account->update(['last_synced_at' => now(), 'status' => $response->successful() ? ChannelAccountStatus::Connected->value : ChannelAccountStatus::Error->value]);

        return ['ok' => $response->successful(), 'synced' => $synced, 'response' => $response->json()];
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

    protected function messagePayload(string $phone, array $payload): array
    {
        if (($payload['type'] ?? null) === 'catalog_message') {
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'catalog_message',
                    'body' => ['text' => (string) ($payload['body'] ?? 'Browse our catalog and add products to your cart.')],
                    'action' => ['name' => 'catalog_message', 'parameters' => array_filter(['thumbnail_product_retailer_id' => $payload['thumbnail_product_retailer_id'] ?? null])],
                    'footer' => ['text' => (string) ($payload['footer'] ?? 'Availability and international shipping are confirmed before payment.')],
                ],
            ];
        }

        if (($payload['type'] ?? null) === 'product') {
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'product',
                    'body' => ['text' => (string) ($payload['body'] ?? 'Product details')],
                    'action' => ['catalog_id' => (string) $payload['catalog_id'], 'product_retailer_id' => (string) $payload['product_retailer_id']],
                ],
            ];
        }

        if (($payload['type'] ?? null) === 'product_list') {
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'product_list',
                    'header' => ['type' => 'text', 'text' => (string) ($payload['header'] ?? 'Our products')],
                    'body' => ['text' => (string) ($payload['body'] ?? 'Select products to add to your cart.')],
                    'action' => ['catalog_id' => (string) $payload['catalog_id'], 'sections' => $payload['sections'] ?? []],
                ],
            ];
        }

        if (($payload['type'] ?? null) === 'template') {
            return [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $payload['template_name'],
                    'language' => ['code' => $payload['language'] ?? 'en_US'],
                    'components' => $payload['components'] ?? [],
                ],
            ];
        }

        if (in_array(($payload['type'] ?? null), ['image', 'video', 'audio', 'document'], true)) {
            $type = (string) $payload['type'];
            $media = [
                'link' => (string) $payload['url'],
            ];

            if (filled($payload['caption'] ?? null) && in_array($type, ['image', 'video', 'document'], true)) {
                $media['caption'] = (string) $payload['caption'];
            }

            if ($type === 'document' && filled($payload['filename'] ?? null)) {
                $media['filename'] = (string) $payload['filename'];
            }

            return [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => $type,
                $type => $media,
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => (string) ($payload['body'] ?? '')],
        ];
    }

    protected function messages(array $payload): array
    {
        return Arr::flatten(data_get($payload, 'entry.*.changes.*.value.messages', []), 1);
    }

    protected function statuses(array $payload): array
    {
        return Arr::flatten(data_get($payload, 'entry.*.changes.*.value.statuses', []), 1);
    }

    protected function eventType(array $payload): string
    {
        if ($this->messages($payload) !== []) {
            return 'message.received';
        }

        if ($this->statuses($payload) !== []) {
            return 'message.status';
        }

        return data_get($payload, 'entry.0.changes.0.field', 'unknown');
    }

    protected function eventId(array $payload): string
    {
        return data_get($payload, 'entry.0.id', 'event').'-'.data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id', 'unknown').'-'.sha1(json_encode($payload));
    }

    protected function persistInboundMessage(ChannelAccount $account, array $message): void
    {
        $providerContactId = (string) ($message['from'] ?? '');

        if ($providerContactId === '') {
            return;
        }

        $phone = $this->normalizeInboundPhone($providerContactId);
        $profileName = (string) data_get($message, 'profile.name', $phone);

        $contact = Contact::query()->firstOrCreate(
            ['workspace_id' => $account->workspace_id, 'phone' => $phone],
            [
                'name' => $profileName,
                'opt_in_status' => ContactOptInStatus::Subscribed->value,
                'opt_in_at' => now(),
                'last_interaction_at' => now(),
            ]
        );

        $contact->forceFill([
            'name' => $contact->name ?: $profileName,
            'opt_in_status' => ContactOptInStatus::Subscribed->value,
            'opt_in_at' => $contact->opt_in_at ?: now(),
            'last_interaction_at' => now(),
        ])->save();

        ContactProviderIdentity::query()->updateOrCreate(
            [
                'workspace_id' => $account->workspace_id,
                'provider' => $this->provider(),
                'provider_contact_id' => $providerContactId,
            ],
            [
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
                'address' => $phone,
                'identity_type' => 'phone',
                'metadata' => ['profile_name' => $profileName],
                'last_interaction_at' => now(),
            ]
        );

        $conversation = Conversation::query()->firstOrCreate(
            ['workspace_id' => $account->workspace_id, 'contact_id' => $contact->id, 'channel_account_id' => $account->id],
            [
                'provider' => $this->provider(),
                'provider_conversation_id' => $providerContactId,
                'status' => ConversationStatus::Open->value,
                'labels' => [],
            ]
        );

        Message::query()->updateOrCreate(
            ['provider_message_id' => $message['id'] ?? Str::uuid()->toString()],
            [
                'workspace_id' => $account->workspace_id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
                'provider' => $this->provider(),
                'direction' => 'inbound',
                'type' => $message['type'] ?? 'text',
                'body' => data_get($message, 'text.body'),
                'payload' => $message,
                'status' => MessageStatus::Received->value,
                'whatsapp_message_id' => $message['id'] ?? null,
            ]
        );

        $conversation->update([
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
            'session_expires_at' => now()->addHours(24),
        ]);

        if (($message['type'] ?? null) === 'order') {
            app(OrderIntakeService::class)->intake($account, $contact, $conversation, $message);
        }
    }

    protected function normalizeInboundPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;

        return str_starts_with($digits, '+') ? $digits : '+'.$digits;
    }

    protected function persistStatus(ChannelAccount $account, array $status): void
    {
        Message::query()
            ->where('workspace_id', $account->workspace_id)
            ->where(function ($query) use ($status): void {
                $query->where('provider_message_id', $status['id'] ?? null)
                    ->orWhere('whatsapp_message_id', $status['id'] ?? null);
            })
            ->update(['status' => $this->messageStatus($status['status'] ?? null)]);
    }

    protected function templateStatus(?string $status): string
    {
        $value = Str::lower((string) $status);
        $value = match ($value) {
            'pending_review' => 'pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'paused' => 'paused',
            'disabled' => 'disabled',
            'in_appeal' => 'in_appeal',
            'pending_deletion' => 'pending_deletion',
            default => $value,
        };

        return MessageTemplateStatus::tryFrom($value)?->value ?? MessageTemplateStatus::Submitted->value;
    }

    protected function refreshTemplateSummaryStatus(MessageTemplate $template): void
    {
        $statuses = $template->submissions()
            ->pluck('status')
            ->map(fn ($status) => $status instanceof MessageTemplateStatus ? $status->value : (string) $status)
            ->all();

        $summary = match (true) {
            in_array(MessageTemplateStatus::Approved->value, $statuses, true) => MessageTemplateStatus::Approved,
            count(array_intersect($statuses, [
                MessageTemplateStatus::Submitted->value,
                MessageTemplateStatus::Pending->value,
                MessageTemplateStatus::InAppeal->value,
            ])) > 0 => MessageTemplateStatus::Pending,
            count(array_intersect($statuses, [
                MessageTemplateStatus::Rejected->value,
                MessageTemplateStatus::Disabled->value,
                MessageTemplateStatus::Paused->value,
                MessageTemplateStatus::PendingDeletion->value,
            ])) > 0 => MessageTemplateStatus::Rejected,
            in_array(MessageTemplateStatus::Failed->value, $statuses, true) => MessageTemplateStatus::Failed,
            default => MessageTemplateStatus::Draft,
        };

        $template->forceFill(['status' => $summary->value])->save();
    }

    protected function messageStatus(?string $status): string
    {
        $value = Str::lower((string) $status);

        return MessageStatus::tryFrom($value)?->value ?? MessageStatus::Sent->value;
    }

    public function testConnection(ChannelAccount $account): array
    {
        $response = $this->client->phoneNumbers(
            (string) $account->provider_account_id,
            (string) $account->credential('access_token')
        );

        return [
            'ok' => $response->successful(),
            'provider' => $this->provider(),
            'response' => $response->json(),
        ];
    }

    public function processWebhook(ChannelAccount $account, array $payload): array
    {
        $events = [];

        foreach ($this->messages($payload) as $message) {
            $events[] = [
                'type' => 'message',
                'provider_message_id' => $message['id'] ?? null,
                'provider_contact_id' => $message['from'] ?? null,
                'body' => data_get($message, 'text.body')
                    ?: data_get($message, 'button.text')
                    ?: data_get($message, 'interactive.button_reply.title')
                    ?: data_get($message, 'interactive.list_reply.title'),
                'payload' => $message,
            ];
        }

        foreach ($this->statuses($payload) as $status) {
            $events[] = [
                'type' => 'status',
                'provider_message_id' => $status['id'] ?? null,
                'status' => $this->messageStatus($status['status'] ?? null),
                'payload' => $status,
            ];
        }

        return ['ok' => true, 'events' => $events];
    }

    public function getCapabilities(): array
    {
        return ['Campaigns', 'Inbox', 'Templates', 'Automation', 'Webhooks', 'Catalog', 'Product Messages', 'Commerce Orders'];
    }

    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
    {
        if (blank($account->provider_account_id)) {
            throw ValidationException::withMessages([
                'channel_account_id' => 'The selected WhatsApp number is missing its WABA connection.',
            ]);
        }

        if ($campaign->message_type === 'automation') {
            return;
        }

        if ($campaign->message_type === 'custom') {
            if (blank($campaign->message_body)) {
                throw ValidationException::withMessages([
                    'message_body' => 'WhatsApp custom campaigns require a message body.',
                ]);
            }

            return;
        }

        if (blank($campaign->message_template_id)) {
            throw ValidationException::withMessages([
                'message_template_id' => 'WhatsApp campaigns require an approved template.',
            ]);
        }

        $template = MessageTemplate::query()
            ->where('workspace_id', $campaign->workspace_id)
            ->where('provider', $this->provider())
            ->whereKey($campaign->message_template_id)
            ->whereHas('submissions', function ($query) use ($account): void {
                $query->where('provider_account_id', $account->provider_account_id)
                    ->where('status', MessageTemplateStatus::Approved->value);
            })
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'message_template_id' => 'This template is not approved for the selected WhatsApp Business Account.',
            ]);
        }
    }
}
