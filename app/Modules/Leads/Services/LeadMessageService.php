<?php

namespace App\Modules\Leads\Services;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\Leads\Models\Lead;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Telegram\Services\TelegramInviteService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadMessageService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected LeadService $leads,
        protected InboxService $inbox,
        protected ChannelManager $channels,
        protected TelegramInviteService $telegramInvites,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function sendForUser(?User $user, string $leadId, array $data): array
    {
        $workspace = $this->workspaces->current($user);
        $lead = Lead::query()->where('workspace_id', $workspace->id)->findOrFail($leadId);
        $contact = $this->contactForLead($user, $lead);
        $channel = (string) $data['channel'];

        return match ($channel) {
            'whatsapp' => $this->sendWhatsApp($user, $contact, (string) ($data['body'] ?? '')),
            'sms' => $this->sendViaDriver($workspace->id, $contact, 'sms', (string) ($data['body'] ?? ''), null),
            'email' => $this->sendViaDriver($workspace->id, $contact, 'email', (string) ($data['body'] ?? ''), (string) ($data['subject'] ?? '')),
            'telegram' => $this->sendTelegram($workspace->id, $contact, $data),
            default => throw ValidationException::withMessages(['channel' => 'Choose a valid channel.']),
        };
    }

    protected function contactForLead(?User $user, Lead $lead): Contact
    {
        if ($lead->contact_id) {
            return Contact::query()->where('workspace_id', $lead->workspace_id)->findOrFail($lead->contact_id);
        }

        return $this->leads->convertForUser($user, (string) $lead->id);
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    protected function sendWhatsApp(?User $user, Contact $contact, string $body): array
    {
        if (! $contact->hasValidPhone()) {
            throw ValidationException::withMessages(['channel' => 'This lead needs a valid phone number for WhatsApp.']);
        }

        $thread = $this->inbox->openForContact($user, (string) $contact->id, 'whatsapp');
        $result = $this->inbox->sendMessage($user, (string) data_get($thread, 'conversation.id'), $body);

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => ($result['ok'] ?? false) ? 'WhatsApp message sent.' : null,
            'error' => ($result['ok'] ?? false) ? null : ($result['error'] ?? 'WhatsApp message could not be sent.'),
        ];
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    protected function sendViaDriver(int $workspaceId, Contact $contact, string $provider, string $body, ?string $subject): array
    {
        $account = $this->connectedAccount($workspaceId, $provider);

        if (! $account) {
            throw ValidationException::withMessages(['channel' => 'Connect a '.Str::headline($provider).' channel before sending.']);
        }

        [$recipient, $payload] = $this->payloadFor($contact, $provider, $body, $subject);
        $result = $this->channels->sendMessage($account, $recipient, $payload);
        $this->recordOutbound($contact, $account, $recipient['to'], $payload, $result);

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => ($result['ok'] ?? false) ? Str::headline($provider).' message sent.' : null,
            'error' => ($result['ok'] ?? false) ? null : ($result['error'] ?? Str::headline($provider).' message could not be sent.'),
        ];
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    protected function sendTelegram(int $workspaceId, Contact $contact, array $data): array
    {
        $identity = ContactProviderIdentity::query()
            ->where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->where('provider', 'telegram')
            ->first();

        if ($identity) {
            return $this->sendViaDriver($workspaceId, $contact, 'telegram', (string) ($data['body'] ?? ''), null);
        }

        $deliveryChannel = (string) ($data['telegram_delivery_channel'] ?? 'copy');
        $result = $this->telegramInvites->send($contact, $deliveryChannel, $data['body'] ?? null);

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $deliveryChannel === 'copy' ? 'Telegram invite link created.' : 'Telegram invite sent.',
            'error' => ($result['ok'] ?? false) ? null : ($result['error'] ?? 'Telegram invite could not be sent.'),
        ];
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     */
    protected function payloadFor(Contact $contact, string $provider, string $body, ?string $subject): array
    {
        if ($provider === 'email') {
            if (! filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['channel' => 'This lead needs a valid email address for email.']);
            }

            return [
                ['to' => (string) $contact->email],
                [
                    'type' => 'email',
                    'subject' => $subject,
                    'html_body' => nl2br(e($body)),
                    'text_body' => $body,
                    'append_unsubscribe' => false,
                ],
            ];
        }

        if (in_array($provider, ['sms', 'telegram'], true) && ! $contact->hasValidPhone() && $provider === 'sms') {
            throw ValidationException::withMessages(['channel' => 'This lead needs a valid phone number for SMS.']);
        }

        if ($provider === 'telegram') {
            $identity = ContactProviderIdentity::query()
                ->where('workspace_id', $contact->workspace_id)
                ->where('contact_id', $contact->id)
                ->where('provider', 'telegram')
                ->first();

            if (! $identity?->provider_contact_id && ! $identity?->address) {
                throw ValidationException::withMessages(['channel' => 'This lead needs Telegram opt-in before direct Telegram messaging.']);
            }

            return [
                ['to' => (string) ($identity->address ?: $identity->provider_contact_id)],
                ['type' => 'text', 'body' => $body],
            ];
        }

        return [
            ['to' => (string) $contact->phone],
            ['type' => 'text', 'body' => $body],
        ];
    }

    protected function connectedAccount(int $workspaceId, string $provider): ?ChannelAccount
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', $provider)
            ->where('status', ChannelAccountStatus::Connected->value)
            ->orderByDesc('connected_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function recordOutbound(Contact $contact, ChannelAccount $account, string $recipient, array $payload, array $result): void
    {
        $conversation = Conversation::query()->firstOrCreate(
            [
                'workspace_id' => $contact->workspace_id,
                'channel_account_id' => $account->id,
                'provider' => $account->provider,
                'contact_id' => $contact->id,
            ],
            [
                'provider_conversation_id' => $recipient,
                'status' => ConversationStatus::Open->value,
                'labels' => [],
            ],
        );

        Message::query()->create([
            'workspace_id' => $contact->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => $account->provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => $payload['type'] ?? 'text',
            'body' => $payload['body'] ?? ($payload['text_body'] ?? null),
            'payload' => ['lead_direct_message' => true, 'payload' => $payload, 'response' => $result['response'] ?? null],
            'status' => ($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value,
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $account->provider === 'whatsapp' ? ($result['provider_message_id'] ?? null) : null,
        ]);

        $conversation->update([
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
        ]);

        $contact->updateQuietly(['last_interaction_at' => now()]);
    }
}
