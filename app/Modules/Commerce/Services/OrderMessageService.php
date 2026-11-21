<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OrderMessageService
{
    /** @var list<string> */
    public const SUPPORTED_PROVIDERS = [
        'whatsapp',
        'sms',
        'email',
        'messenger',
        'instagram',
        'telegram',
    ];

    /** @var list<string> */
    private const INBOX_PROVIDERS = [
        'whatsapp',
        'messenger',
        'instagram',
        'telegram',
    ];

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ChannelManager $channels,
        protected ContactService $contacts,
    ) {}

    /**
     * Returns all active (connected) channel accounts in the workspace that support direct customer messaging.
     *
     * @return Collection<int, ChannelAccount>
     */
    public function activeChannelAccounts(int $workspaceId): Collection
    {
        $priority = array_flip(self::SUPPORTED_PROVIDERS);

        $accounts = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', ChannelAccountStatus::Connected->value)
            ->whereIn('provider', self::SUPPORTED_PROVIDERS)
            ->orderByDesc('connected_at')
            ->orderByDesc('id')
            ->get();

        return $accounts
            ->sortBy(fn (ChannelAccount $account): int => $priority[$account->provider] ?? 999)
            ->values();
    }

    /**
     * Returns only the active workspace channels on which this order's customer can actually be contacted.
     *
     * @param  Collection<int, ChannelAccount>|null  $activeAccounts
     * @return list<array{
     *     channel_account_id: int,
     *     provider: string,
     *     provider_label: string,
     *     icon: string,
     *     account_name: string,
     *     account_display: ?string,
     *     recipient: string,
     *     requires_subject: bool,
     * }>
     */
    public function availableChannelsForOrder(Order $order, ?Collection $activeAccounts = null): array
    {
        $accounts = $activeAccounts ?? $this->activeChannelAccounts((int) $order->workspace_id);
        $available = [];

        foreach ($accounts as $account) {
            if (
                (int) $account->workspace_id !== (int) $order->workspace_id
                || $account->status !== ChannelAccountStatus::Connected
                || ! in_array($account->provider, self::SUPPORTED_PROVIDERS, true)
            ) {
                continue;
            }

            $recipient = $this->resolveRecipient($order, $account);

            if ($recipient === null) {
                continue;
            }

            $available[] = [
                'channel_account_id' => $account->id,
                'provider' => $account->provider,
                'provider_label' => $this->providerLabel($account->provider),
                'icon' => $this->providerIcon($account->provider),
                'account_name' => $account->name,
                'account_display' => filled($account->provider_display_id) ? (string) $account->provider_display_id : null,
                'recipient' => $recipient,
                'requires_subject' => $account->provider === 'email',
            ];
        }

        return $available;
    }

    /**
     * Builds the client payload for the direct-message modal on orders pages.
     *
     * @param  Collection<int, ChannelAccount>|null  $activeAccounts
     * @return array{
     *     order_id: int,
     *     order_number: string,
     *     customer_name: string,
     *     send_url: string,
     *     channels_url: string,
     *     has_workspace_channels: bool,
     *     default_subject: string,
     *     channels: list<array<string, mixed>>,
     * }
     */
    public function orderModalPayload(Order $order, ?Collection $activeAccounts = null): array
    {
        $accounts = $activeAccounts ?? $this->activeChannelAccounts((int) $order->workspace_id);
        $customerName = $order->contact?->name
            ?: ($order->customer_snapshot['name'] ?? null)
            ?: ($order->shipping_address['name'] ?? null)
            ?: $order->contact?->phone
            ?: __('Customer');

        return [
            'order_id' => $order->id,
            'order_number' => $order->number,
            'customer_name' => (string) $customerName,
            'send_url' => route('user.commerce.orders.message.send', $order),
            'channels_url' => route('user.channels.index'),
            'has_workspace_channels' => $accounts->isNotEmpty(),
            'default_subject' => __('Update regarding order :number', ['number' => $order->number]),
            'channels' => $this->availableChannelsForOrder($order, $accounts),
        ];
    }

    /**
     * Sends a direct message to the order's customer through the chosen active channel account.
     *
     * @param  array{channel_account_id: int|string, body: string, subject?: ?string}  $data
     * @return array{
     *     ok: bool,
     *     channel: string,
     *     channel_label: string,
     *     account_name: string,
     *     recipient: string,
     *     conversation_id: int,
     *     conversation_url: ?string,
     *     message: ?string,
     *     error: ?string,
     *     error_detail: ?string,
     * }
     */
    public function send(?User $user, Order $order, array $data): array
    {
        $workspace = $this->workspaces->current($user);

        if ((int) $order->workspace_id !== (int) $workspace->id) {
            abort(404);
        }

        $order->loadMissing('contact.identities');

        $account = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', ChannelAccountStatus::Connected->value)
            ->whereIn('provider', self::SUPPORTED_PROVIDERS)
            ->find((int) $data['channel_account_id']);

        if (! $account) {
            throw ValidationException::withMessages([
                'channel_account_id' => __('The selected channel is not active or available.'),
            ]);
        }

        $recipient = $this->resolveRecipient($order, $account);

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'channel_account_id' => __('This customer cannot be reached on :channel.', [
                    'channel' => $this->providerLabel($account->provider),
                ]),
            ]);
        }

        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('Enter a message to send to the customer.'),
            ]);
        }

        $subject = trim((string) ($data['subject'] ?? ''));

        if ($account->provider === 'email' && $subject === '') {
            throw ValidationException::withMessages([
                'subject' => __('Enter a subject for the email message.'),
            ]);
        }

        $contact = $this->resolveOrCreateContact($order, $account->provider, $recipient);
        $conversation = $this->resolveConversation($contact, $account, $recipient);

        $payload = $this->buildPayload($account, $conversation, $body, $subject);
        $recipientPayload = $this->buildRecipientPayload($account->provider, $recipient);

        try {
            $result = $this->channels->sendMessage($account, $recipientPayload, $payload);
        } catch (\Throwable $exception) {
            $result = [
                'ok' => false,
                'status' => MessageStatus::Failed->value,
                'error' => $exception->getMessage(),
                'response' => ['error' => ['message' => $exception->getMessage()]],
            ];
        }

        $ok = (bool) ($result['ok'] ?? false);

        Message::query()->create([
            'workspace_id' => $order->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => $account->provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => $payload['type'] ?? 'text',
            'body' => $payload['body'] ?? ($payload['text_body'] ?? $body),
            'payload' => [
                'order_direct_message' => true,
                'order_id' => $order->id,
                'order_number' => $order->number,
                'sent_by_user_id' => $user?->id,
                'payload' => $payload,
                'response' => $result['response'] ?? null,
            ],
            'status' => ($result['status'] ?? null) ?: ($ok ? MessageStatus::Sent->value : MessageStatus::Failed->value),
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $account->provider === 'whatsapp' ? ($result['provider_message_id'] ?? null) : null,
        ]);

        $conversation->forceFill([
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
        ])->save();

        $contact->updateQuietly(['last_interaction_at' => now()]);

        $isInboxProvider = in_array($account->provider, self::INBOX_PROVIDERS, true);

        if ($order->conversation_id === null && $isInboxProvider) {
            $order->forceFill(['conversation_id' => $conversation->id])->save();
        }

        $channelLabel = $this->providerLabel($account->provider);

        return [
            'ok' => $ok,
            'channel' => $account->provider,
            'channel_label' => $channelLabel,
            'account_name' => $account->name,
            'recipient' => $recipient,
            'conversation_id' => $conversation->id,
            'conversation_url' => $isInboxProvider ? route('user.inbox.index', ['conversation' => $conversation->id]) : null,
            'message' => $ok
                ? __('Message sent via :channel to :recipient.', ['channel' => $channelLabel, 'recipient' => $recipient])
                : null,
            'error' => $ok
                ? null
                : ($result['error'] ?? data_get($result, 'response.error.message', __('Message could not be sent.'))),
            'error_detail' => $ok ? null : ($result['error_detail'] ?? null),
        ];
    }

    protected function resolveRecipient(Order $order, ChannelAccount $account): ?string
    {
        return match ($account->provider) {
            'whatsapp' => $this->resolvePhoneRecipient($order) ?: $this->resolveIdentityRecipient($order, $account),
            'sms' => $this->resolvePhoneRecipient($order),
            'email' => $this->resolveEmailRecipient($order),
            'telegram', 'messenger', 'instagram' => $this->resolveIdentityRecipient($order, $account),
            default => null,
        };
    }

    protected function resolvePhoneRecipient(Order $order): ?string
    {
        $candidates = [
            $order->contact?->phone,
            $order->customer_snapshot['phone'] ?? null,
            $order->shipping_address['phone'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $normalized = $this->normalizeE164($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    protected function normalizeE164(string $phone): ?string
    {
        try {
            return $this->contacts->normalizePhone($phone);
        } catch (ValidationException) {
            $compact = preg_replace('/[\s().\-\/]+/', '', trim($phone)) ?? trim($phone);

            if (str_starts_with($compact, '00')) {
                $compact = '+'.substr($compact, 2);
            }

            return preg_match('/^\+[1-9]\d{7,14}$/', $compact) === 1 ? $compact : null;
        }
    }

    protected function resolveEmailRecipient(Order $order): ?string
    {
        $candidates = [
            $order->contact?->email,
            $order->customer_snapshot['email'] ?? null,
            $order->shipping_address['email'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $email = trim($candidate);

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    protected function resolveIdentityRecipient(Order $order, ChannelAccount $account): ?string
    {
        $contact = $order->contact;

        if (! $contact) {
            return null;
        }

        $identities = $contact->relationLoaded('identities')
            ? $contact->identities->where('provider', $account->provider)
            : $contact->identities()->where('provider', $account->provider)->get();

        $usable = $identities
            ->filter(fn (ContactProviderIdentity $identity): bool => filled($identity->provider_contact_id) || filled($identity->address))
            ->sortByDesc(fn (ContactProviderIdentity $identity): int => (int) ($identity->last_interaction_at?->timestamp ?? $identity->id));

        $matched = $usable->first(fn (ContactProviderIdentity $identity): bool => (int) $identity->channel_account_id === (int) $account->id)
            ?? $usable->first(fn (ContactProviderIdentity $identity): bool => $identity->channel_account_id === null);

        if (! $matched) {
            return null;
        }

        if ($account->provider === 'telegram') {
            return (string) ($matched->address ?: $matched->provider_contact_id);
        }

        return (string) ($matched->provider_contact_id ?: $matched->address);
    }

    protected function resolveOrCreateContact(Order $order, string $provider, string $recipient): Contact
    {
        if ($order->contact) {
            return $order->contact;
        }

        $phone = $this->resolvePhoneRecipient($order);
        $email = $this->resolveEmailRecipient($order);

        $existing = Contact::query()
            ->where('workspace_id', $order->workspace_id)
            ->where(function ($query) use ($phone, $email): void {
                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
                if ($email !== null) {
                    $query->orWhere('email', $email);
                }
            })
            ->first();

        if ($existing) {
            $order->forceFill(['contact_id' => $existing->id])->save();

            return $existing;
        }

        $name = trim((string) (
            ($order->customer_snapshot['name'] ?? null)
            ?: ($order->shipping_address['name'] ?? null)
            ?: $recipient
        ));

        $contact = Contact::query()->create([
            'workspace_id' => $order->workspace_id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'source' => ContactSource::Manual,
            'opt_in_status' => ContactOptInStatus::Unknown,
        ]);

        $order->forceFill(['contact_id' => $contact->id])->save();

        return $contact;
    }

    protected function resolveConversation(Contact $contact, ChannelAccount $account, string $recipient): Conversation
    {
        $providerConversationId = $account->provider === 'whatsapp'
            ? ltrim($recipient, '+')
            : $recipient;

        return Conversation::query()->firstOrCreate(
            [
                'workspace_id' => $contact->workspace_id,
                'channel_account_id' => $account->id,
                'provider' => $account->provider,
                'contact_id' => $contact->id,
            ],
            [
                'provider_conversation_id' => $providerConversationId,
                'status' => ConversationStatus::Open->value,
                'labels' => [],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(ChannelAccount $account, Conversation $conversation, string $body, string $subject): array
    {
        if ($account->provider === 'email') {
            return [
                'type' => 'email',
                'subject' => $subject,
                'html_body' => nl2br(e($body)),
                'text_body' => $body,
                'append_unsubscribe' => false,
            ];
        }

        $payload = [
            'type' => 'text',
            'body' => $body,
        ];

        if (in_array($account->provider, ['messenger', 'instagram'], true) && $this->outsideStandardMessagingWindow($conversation)) {
            $payload['human_agent'] = true;
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    protected function buildRecipientPayload(string $provider, string $recipient): array
    {
        return match ($provider) {
            'whatsapp', 'sms' => ['to' => $recipient, 'phone' => $recipient],
            'email' => ['to' => $recipient, 'email' => $recipient],
            'telegram' => ['to' => $recipient, 'chat_id' => $recipient],
            default => ['to' => $recipient, 'id' => $recipient, 'provider_contact_id' => $recipient],
        };
    }

    protected function outsideStandardMessagingWindow(Conversation $conversation): bool
    {
        $lastInboundAt = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->max('created_at');

        return $lastInboundAt !== null && Carbon::parse($lastInboundAt)->lt(now()->subDay());
    }

    protected function providerLabel(string $provider): string
    {
        return __(config("marketing-channels.providers.{$provider}.label", ucfirst($provider)));
    }

    protected function providerIcon(string $provider): string
    {
        return (string) config("marketing-channels.providers.{$provider}.icon", 'ph-chats-circle');
    }
}
