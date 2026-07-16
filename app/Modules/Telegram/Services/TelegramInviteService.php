<?php

namespace App\Modules\Telegram\Services;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use Illuminate\Validation\ValidationException;

class TelegramInviteService
{
    public function __construct(
        protected TelegramOptInService $optIns,
        protected ChannelManager $channels,
    ) {}

    public function send(Contact $contact, string $deliveryChannel, ?string $message = null): array
    {
        $link = $this->optIns->linkFor($contact);

        if (! $link) {
            throw ValidationException::withMessages([
                'telegram' => 'Connect a Telegram bot before sending invite links.',
            ]);
        }

        $body = $this->renderMessage($contact, $link['url'], $message);

        if ($deliveryChannel === 'copy') {
            return [
                'ok' => true,
                'channel' => 'copy',
                'invite_url' => $link['url'],
                'message' => $body,
            ];
        }

        $account = $this->connectedAccount((int) $contact->workspace_id, $deliveryChannel);

        if (! $account) {
            throw ValidationException::withMessages([
                'channel' => 'Connect a '.ucfirst($deliveryChannel).' channel before sending this invite.',
            ]);
        }

        [$recipient, $payload] = $this->payloadFor($deliveryChannel, $contact, $body);
        $result = $this->channels->sendMessage($account, $recipient, $payload);

        if ($deliveryChannel === 'whatsapp') {
            $this->recordWhatsAppInvite($contact, $account, $body, $link['url'], $result);
        }

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'channel' => $deliveryChannel,
            'invite_url' => $link['url'],
            'message' => $body,
            'provider_response' => $result,
            'error' => ($result['ok'] ?? false) ? null : ($result['error'] ?? 'Invite failed to send.'),
        ];
    }

    public function defaultMessage(Contact $contact, string $link = '{{ telegram_link }}'): string
    {
        return 'Hi {{ name }}, click this link to connect with us on Telegram: '.$link;
    }

    protected function renderMessage(Contact $contact, string $link, ?string $message): string
    {
        $message = filled($message) ? (string) $message : $this->defaultMessage($contact);

        return strtr($message, [
            '{{ name }}' => $contact->name ?: 'there',
            '{{name}}' => $contact->name ?: 'there',
            '{{ telegram_link }}' => $link,
            '{{telegram_link}}' => $link,
        ]);
    }

    protected function payloadFor(string $deliveryChannel, Contact $contact, string $body): array
    {
        return match ($deliveryChannel) {
            'whatsapp' => [
                ['to' => $this->phoneRecipient($contact)],
                ['type' => 'text', 'body' => $body],
            ],
            'sms' => [
                ['to' => $this->phoneRecipient($contact)],
                ['type' => 'text', 'body' => $body],
            ],
            'email' => [
                ['to' => $this->emailRecipient($contact)],
                [
                    'type' => 'email',
                    'subject' => 'Connect with us on Telegram',
                    'html_body' => nl2br(e($body)),
                    'text_body' => $body,
                    'append_unsubscribe' => false,
                ],
            ],
            default => throw ValidationException::withMessages([
                'channel' => 'Choose copy, WhatsApp, SMS, or email.',
            ]),
        };
    }

    protected function phoneRecipient(Contact $contact): string
    {
        if (! $contact->hasValidPhone()) {
            throw ValidationException::withMessages([
                'channel' => 'This contact needs a valid E.164 phone number for this delivery channel.',
            ]);
        }

        return (string) $contact->phone;
    }

    protected function emailRecipient(Contact $contact): string
    {
        if (! filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'channel' => 'This contact needs a valid email address for email delivery.',
            ]);
        }

        return (string) $contact->email;
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

    protected function recordWhatsAppInvite(Contact $contact, ChannelAccount $account, string $body, string $inviteUrl, array $result): void
    {
        $conversation = Conversation::query()->firstOrCreate(
            [
                'workspace_id' => $contact->workspace_id,
                'channel_account_id' => $account->id,
                'provider' => 'whatsapp',
                'contact_id' => $contact->id,
            ],
            [
                'provider_conversation_id' => $contact->phone,
                'status' => ConversationStatus::Open->value,
                'labels' => [],
            ]
        );

        Message::query()->create([
            'workspace_id' => $contact->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => 'whatsapp',
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $body,
            'payload' => [
                'telegram_invite_url' => $inviteUrl,
                'response' => $result['response'] ?? null,
            ],
            'status' => ($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value,
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $result['provider_message_id'] ?? null,
        ]);

        $conversation->update([
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
        ]);
    }
}
