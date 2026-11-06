<?php

namespace App\Modules\MetaSocial\Services;

use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MetaSocial\Models\MessengerMarketingOptin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Marketing messages on Messenger (marketing_messages_messenger): the Page asks a customer, inside the
 * 24-hour window, to "Get messages"; Meta then sends a notification_messages_token that campaigns use.
 */
class MessengerMarketingService
{
    public const DEFAULT_TOPIC = 'offers';

    public const DEFAULT_TITLE = 'Get our offers and new arrivals';

    public function __construct(protected ChannelManager $channels) {}

    /**
     * @return array{auto_request: bool, title: string}
     */
    public function settings(ChannelAccount $account): array
    {
        $settings = (array) data_get($account->settings, 'marketing_messages', []);

        return [
            'auto_request' => (bool) ($settings['auto_request'] ?? false),
            'title' => trim((string) ($settings['title'] ?? '')) ?: self::DEFAULT_TITLE,
        ];
    }

    /**
     * @param  array{auto_request: bool, title: string}  $settings
     */
    public function updateSettings(ChannelAccount $account, array $settings): void
    {
        $account->forceFill([
            'settings' => array_merge((array) $account->settings, [
                'marketing_messages' => [
                    'auto_request' => (bool) $settings['auto_request'],
                    'title' => mb_substr(trim((string) $settings['title']), 0, 65),
                ],
            ]),
        ])->save();
    }

    /**
     * Sends the "Get messages" opt-in request to the customer in this Messenger chat.
     *
     * @return array{ok: bool, error?: string, message?: Message}
     */
    public function requestOptin(Conversation $conversation): array
    {
        $conversation->loadMissing('channelAccount');
        $account = $conversation->channelAccount;

        if (! $account || $account->provider !== 'messenger') {
            return ['ok' => false, 'error' => __('Offer subscriptions are only available on Messenger.')];
        }

        $psid = $this->psid($conversation);

        if ($psid === null) {
            return ['ok' => false, 'error' => __('This chat has no Messenger customer to ask.')];
        }

        if ($this->usableOptin($account, $psid)) {
            return ['ok' => false, 'error' => __('This customer already gets your offers.')];
        }

        if (! $this->insideMessagingWindow($conversation)) {
            return ['ok' => false, 'error' => __('Meta only allows this request within 24 hours of the customer\'s last message.')];
        }

        $title = $this->settings($account)['title'];
        $result = $this->channels->sendMessage($account, ['id' => $psid], [
            'body' => $title,
            'meta_payload' => [
                'recipient' => ['id' => $psid],
                'message' => [
                    'attachment' => [
                        'type' => 'template',
                        'payload' => [
                            'template_type' => 'notification_messages',
                            'title' => $title,
                            'payload' => self::DEFAULT_TOPIC,
                            'notification_messages_cta_text' => 'GET_UPDATES',
                            'notification_messages_reoptin' => 'ENABLED',
                        ],
                    ],
                ],
            ],
        ]);

        $message = Message::query()->create([
            'workspace_id' => $conversation->workspace_id,
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'channel_account_id' => $account->id,
            'provider' => 'messenger',
            'direction' => 'outbound',
            'type' => 'template',
            'body' => $title,
            'provider_message_id' => $result['provider_message_id'] ?? 'local-'.Str::uuid(),
            'payload' => array_filter([
                'marketing_optin_request' => true,
                'response' => ($result['ok'] ?? false) ? null : ['error' => ['message' => (string) ($result['error'] ?? data_get($result, 'response.error.message', ''))]],
            ]),
            'status' => ($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value,
        ]);

        if (! ($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? data_get($result, 'response.error.message') ?? __('Meta did not accept the request.')), 'message' => $message];
        }

        MessengerMarketingOptin::query()->firstOrCreate(
            ['channel_account_id' => $account->id, 'psid' => $psid, 'topic' => self::DEFAULT_TOPIC],
            ['workspace_id' => $account->workspace_id, 'contact_id' => $conversation->contact_id, 'status' => MessengerMarketingOptin::STATUS_PENDING],
        )->forceFill(['requested_at' => now()])->save();

        $conversation->update(['last_message_at' => now()]);

        return ['ok' => true, 'message' => $message];
    }

    /**
     * Asks a new Messenger customer once, after their first message, when the Page owner turned that on.
     */
    public function maybeAutoRequest(ChannelAccount $account, Conversation $conversation): void
    {
        if ($account->provider !== 'messenger' || ! $this->settings($account)['auto_request']) {
            return;
        }

        $psid = $this->psid($conversation);

        if ($psid === null || MessengerMarketingOptin::query()->where('channel_account_id', $account->id)->where('psid', $psid)->exists()) {
            return;
        }

        $this->requestOptin($conversation);
    }

    /**
     * Stores a messaging_optins webhook item: a new token, a STOP or a RESUME.
     *
     * @param  array<string, mixed>  $event
     */
    public function recordOptin(ChannelAccount $account, array $event): ?MessengerMarketingOptin
    {
        $optin = (array) data_get($event, 'optin', []);
        $psid = (string) data_get($event, 'sender.id', '');

        if ($psid === '' || ($optin['type'] ?? null) !== 'notification_messages') {
            return null;
        }

        $identity = ContactProviderIdentity::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('provider', 'messenger')
            ->where('provider_contact_id', $psid)
            ->first();

        if (! $identity) {
            return null;
        }

        $topic = mb_substr(trim((string) ($optin['payload'] ?? '')) ?: self::DEFAULT_TOPIC, 0, 100);
        $record = MessengerMarketingOptin::query()->firstOrNew(
            ['channel_account_id' => $account->id, 'psid' => $psid, 'topic' => $topic],
        );
        $record->fill(['workspace_id' => $account->workspace_id, 'contact_id' => $identity->contact_id]);

        $status = (string) ($optin['notification_messages_status'] ?? '');

        if ($status === 'STOP_NOTIFICATIONS') {
            $record->fill(['status' => MessengerMarketingOptin::STATUS_STOPPED, 'stopped_at' => now()]);
        } else {
            if (filled($optin['notification_messages_token'] ?? null)) {
                $record->token = (string) $optin['notification_messages_token'];
            }

            $record->fill([
                'status' => filled($record->token) ? MessengerMarketingOptin::STATUS_ACTIVE : MessengerMarketingOptin::STATUS_PENDING,
                'frequency' => $optin['notification_messages_frequency'] ?? $record->frequency,
                'expires_at' => $this->expiry($optin['token_expiry_timestamp'] ?? null) ?? $record->expires_at,
                'opted_in_at' => $record->opted_in_at ?? now(),
                'stopped_at' => null,
            ]);
        }

        $record->save();

        return $record;
    }

    public function tokenFor(int $contactId, int $channelAccountId): ?string
    {
        $token = MessengerMarketingOptin::query()
            ->usable()
            ->where('contact_id', $contactId)
            ->where('channel_account_id', $channelAccountId)
            ->latest('opted_in_at')
            ->first()?->token;

        return filled($token) ? (string) $token : null;
    }

    public function subscriberCount(ChannelAccount $account): int
    {
        return MessengerMarketingOptin::query()->usable()->where('channel_account_id', $account->id)->distinct()->count('contact_id');
    }

    public function isSubscribed(Conversation $conversation): bool
    {
        return $conversation->provider === 'messenger'
            && MessengerMarketingOptin::query()->usable()
                ->where('channel_account_id', $conversation->channel_account_id)
                ->where('contact_id', $conversation->contact_id)
                ->exists();
    }

    protected function usableOptin(ChannelAccount $account, string $psid): bool
    {
        return MessengerMarketingOptin::query()->usable()->where('channel_account_id', $account->id)->where('psid', $psid)->exists();
    }

    protected function psid(Conversation $conversation): ?string
    {
        $psid = ContactProviderIdentity::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('provider', 'messenger')
            ->where('contact_id', $conversation->contact_id)
            ->value('provider_contact_id') ?: $conversation->provider_conversation_id;

        return filled($psid) ? (string) $psid : null;
    }

    protected function insideMessagingWindow(Conversation $conversation): bool
    {
        $lastInboundAt = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->max('created_at');

        return $lastInboundAt !== null && Carbon::parse($lastInboundAt)->gt(now()->subDay());
    }

    protected function expiry(mixed $timestamp): ?Carbon
    {
        if (! is_numeric($timestamp) || (int) $timestamp <= 0) {
            return null;
        }

        $seconds = (int) $timestamp > 9999999999 ? intdiv((int) $timestamp, 1000) : (int) $timestamp;

        return Carbon::createFromTimestamp($seconds);
    }
}
