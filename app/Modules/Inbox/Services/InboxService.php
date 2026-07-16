<?php

namespace App\Modules\Inbox\Services;

use App\Models\User;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\Campaigns\Services\TemplateVariableMapper;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Chatbots\Models\ChatbotWidgetSession;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use App\Modules\Telegram\Services\TelegramOptInService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InboxService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ChannelManager $channels,
        protected TemplateVariableMapper $variables,
        protected AutomationDispatcher $automations,
        protected TelegramOptInService $telegramOptIns,
    ) {}

    public function conversationsForUser(?User $user, array $filters = []): array
    {
        $workspace = $this->workspaces->current($user);
        $provider = $this->providerFilter($filters['provider'] ?? null);

        $query = Conversation::query()
            ->with(['contact.tags', 'channelAccount'])
            ->where('workspace_id', $workspace->id)
            ->whereIn('provider', $this->inboxProviders())
            ->tap(fn (Builder $query) => $this->onlyConnectedInboxChannels($query))
            ->when($provider, fn (Builder $query) => $query->where('provider', $provider))
            ->when($this->shouldLimitToAssignedConversations($user), fn ($q) => $q->where('assigned_to', $user?->id))
            ->when(($filters['status'] ?? 'all') !== 'all', function ($query) use ($filters): void {
                if ($filters['status'] === 'unread') {
                    $query->whereHas('messages', fn ($messageQuery) => $messageQuery->where('direction', 'inbound'));

                    return;
                }

                $query->where('status', $filters['status']);
            })
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $search = trim((string) $filters['q']);
                $query->where(function ($nested) use ($search): void {
                    $nested->whereHas('contact', function ($contactQuery) use ($search): void {
                        $contactQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('messages', fn ($messageQuery) => $messageQuery->where('body', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50);

        $conversations = $query->get();
        $latestMessages = $this->latestMessagesFor($conversations);

        return [
            'conversations' => $conversations
                ->map(fn (Conversation $conversation): array => $this->formatConversation($conversation, $latestMessages->get($conversation->id)))
                ->values()
                ->all(),
            'counts' => $this->counts($workspace->id, $provider),
            'channels' => $this->channelOptions($workspace->id),
            'has_channel' => $provider
                ? $this->hasReplyChannel($workspace->id, $provider)
                : $this->hasConnectedInboxChannel($workspace->id),
        ];
    }

    public function conversationForUser(?User $user, string|int $conversationId): array
    {
        $conversation = $this->conversationModelForUser($user, $conversationId);

        return $this->threadPayload($conversation);
    }

    public function conversationModelForUser(?User $user, string|int $conversationId): Conversation
    {
        $workspace = $this->workspaces->current($user);

        return $this->conversationQuery($workspace->id, $user)->findOrFail($conversationId);
    }

    public function openForContact(?User $user, string|int $contactId, ?string $provider = null): array
    {
        $workspace = $this->workspaces->current($user);
        $provider = $this->providerFilter($provider) ?: 'whatsapp';
        $contact = Contact::query()
            ->where('workspace_id', $workspace->id)
            ->when($this->shouldLimitToAssignedContacts($user), fn ($q) => $q->where('assigned_to', $user?->id))
            ->findOrFail($contactId);

        $account = $this->connectedAccount($workspace->id, $provider);
        $providerContactId = $this->providerContactId($contact, $provider);

        $lookup = [
            'workspace_id' => $workspace->id,
            'provider' => $provider,
            'contact_id' => $contact->id,
        ];

        if ($account) {
            $lookup['channel_account_id'] = $account->id;
        }

        $conversation = Conversation::query()->firstOrCreate($lookup, [
            'channel_account_id' => $account?->id,
            'provider_conversation_id' => $providerContactId,
            'status' => ConversationStatus::Open->value,
            'labels' => [],
        ]);

        if ($conversation->wasRecentlyCreated) {
            rescue(function () use ($workspace, $conversation, $contact, $provider, $account): void {
                $this->automations->dispatch([
                    'type' => 'conversation_opened',
                    'workspace_id' => $workspace->id,
                    'provider' => $provider,
                    'channel_account_id' => $account?->id,
                    'contact_id' => $contact->id,
                    'conversation_id' => $conversation->id,
                    'event_key' => 'conversation-opened:'.$conversation->id,
                ]);
            }, report: false);
        }

        return $this->threadPayload($conversation->fresh(['contact.tags', 'channelAccount']));
    }

    public function sendMessage(?User $user, string|int $conversationId, string $body, ?UploadedFile $attachment = null): array
    {
        $workspace = $this->workspaces->current($user);
        app(SubscriptionAccessService::class)->assertActiveForUse((int) $workspace->id);

        $conversation = $this->conversationQuery($workspace->id, $user)->findOrFail($conversationId);
        $contact = $conversation->contact;
        $provider = (string) $conversation->provider;
        $recipient = $this->recipientFor($contact, $provider);
        $body = trim($body);

        $account = $this->connectedConversationAccount($conversation) ?: $this->connectedAccount($workspace->id, $provider);

        if ($attachment && ! $this->supportsAttachments($provider)) {
            throw ValidationException::withMessages([
                'attachment' => __('File attachments are not supported for :channel inbox conversations.', [
                    'channel' => $this->providerLabel($provider),
                ]),
            ]);
        }

        if ($provider === 'website_widget') {
            $widget = $this->widgetForConversation($conversation);

            if ($widget?->automatedReplyEnabled()) {
                throw ValidationException::withMessages([
                    'body' => __('Automated chatbot replies are enabled for this widget. Turn them off before replying from Inbox.'),
                ]);
            }

            $attachmentMeta = $attachment ? $this->storeAttachment($workspace->id, $attachment) : null;

            return $this->recordWebsiteWidgetReply($workspace->id, $conversation, $contact, $body, $attachmentMeta);
        }

        if (! $contact || blank($recipient)) {
            return $this->recordFailedSend(
                $workspace->id,
                $conversation,
                $contact,
                $account,
                $provider,
                $body,
                null,
                __('This conversation does not have a :channel recipient.', [
                    'channel' => $this->providerLabel($provider),
                ])
            );
        }

        if (! $account) {
            return $this->recordFailedSend(
                $workspace->id,
                $conversation,
                $contact,
                null,
                $provider,
                $body,
                null,
                __('Connect a :channel channel before sending inbox replies.', [
                    'channel' => $this->providerLabel($provider),
                ])
            );
        }

        $attachmentMeta = $attachment ? $this->storeAttachment($workspace->id, $attachment) : null;
        $payload = $this->outboundPayload($body, $attachmentMeta);

        if ($provider === 'threads') {
            $replyToId = $this->threadsReplyTarget($conversation);

            if (blank($replyToId)) {
                return $this->recordFailedSend(
                    $workspace->id,
                    $conversation,
                    $contact,
                    $account,
                    $provider,
                    $body,
                    null,
                    __('This Threads conversation does not have a reply target yet. Wait for a Threads reply/comment before responding.')
                );
            }

            $payload['reply_to_id'] = $replyToId;
        }

        try {
            $result = $this->channels->sendMessage($account, ['to' => $recipient, 'provider_contact_id' => $recipient], $payload);
        } catch (\Throwable $exception) {
            $result = [
                'ok' => false,
                'status' => MessageStatus::Failed->value,
                'response' => ['error' => ['message' => $exception->getMessage()]],
            ];
        }

        $message = Message::query()->create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $account->id,
            'provider' => $provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => $attachmentMeta['type'] ?? 'text',
            'body' => $body !== '' ? $body : ($attachmentMeta['name'] ?? null),
            'payload' => [
                'attachment' => $attachmentMeta,
                'response' => $result['response'] ?? null,
            ],
            'status' => ($result['status'] ?? null) ?: (($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value),
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $provider === 'whatsapp' ? ($result['provider_message_id'] ?? null) : null,
        ]);

        $conversation->forceFill([
            'channel_account_id' => $account->id,
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
        ])->save();

        $contact->updateQuietly(['last_interaction_at' => now()]);

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $this->formatMessage($message),
            'conversation' => $this->formatConversation($conversation->fresh(['contact.tags', 'channelAccount']), $message),
            'error' => ($result['ok'] ?? false) ? null : ($result['error'] ?? data_get($result, 'response.error.message', __('Message failed to send.'))),
        ];
    }

    public function updateAutomation(?User $user, string|int $conversationId, bool $enabled): array
    {
        $conversation = $this->conversationModelForUser($user, $conversationId);

        if ($conversation->provider !== 'website_widget') {
            throw ValidationException::withMessages([
                'automated_reply_enabled' => __('Automated chatbot replies can only be changed for website widget conversations.'),
            ]);
        }

        $widget = $this->widgetForConversation($conversation);

        if (! $widget) {
            throw ValidationException::withMessages([
                'automated_reply_enabled' => __('This website widget conversation no longer has a widget configuration.'),
            ]);
        }

        $settings = $widget->settings ?? [];
        $settings['automated_reply_enabled'] = $enabled;
        $widget->forceFill(['settings' => $settings])->save();

        return $this->threadPayload($conversation->fresh(['contact.tags', 'channelAccount']));
    }

    protected function conversationQuery(int $workspaceId, ?User $user = null): Builder
    {
        return Conversation::query()
            ->with(['contact.tags', 'channelAccount'])
            ->where('workspace_id', $workspaceId)
            ->whereIn('provider', $this->inboxProviders())
            ->tap(fn (Builder $query) => $this->onlyConnectedInboxChannels($query))
            ->when($this->shouldLimitToAssignedConversations($user), fn ($query) => $query->where('assigned_to', $user?->id));
    }

    protected function onlyConnectedInboxChannels(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('provider', 'website_widget')
                ->orWhereHas('channelAccount', fn (Builder $accountQuery) => $accountQuery->where('status', ChannelAccountStatus::Connected->value));
        });
    }

    protected function shouldLimitToAssignedConversations(?User $user): bool
    {
        return $user !== null
            && ! $user->can('inbox.view')
            && $user->can('inbox.assigned_only');
    }

    protected function shouldLimitToAssignedContacts(?User $user): bool
    {
        return $user !== null
            && ! $user->can('contacts.view')
            && $user->can('contacts.assigned_only');
    }

    protected function threadPayload(Conversation $conversation): array
    {
        $conversation->loadMissing(['contact.tags', 'channelAccount']);

        $messages = Message::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get();

        return [
            'conversation' => $this->formatConversation($conversation, $messages->last()),
            'messages' => $messages->map(fn (Message $message): array => $this->formatMessage($message))->values()->all(),
            'has_channel' => $this->hasReplyChannel((int) $conversation->workspace_id, (string) $conversation->provider),
            'recipient_ready' => $this->recipientReady($conversation),
            'telegram_opt_in' => $this->telegramOptInPayload($conversation),
        ];
    }

    protected function latestMessagesFor(Collection $conversations): Collection
    {
        if ($conversations->isEmpty()) {
            return new Collection;
        }

        return Message::query()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->unique('conversation_id')
            ->keyBy('conversation_id');
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

    protected function connectedConversationAccount(Conversation $conversation): ?ChannelAccount
    {
        $account = $conversation->channelAccount;

        if ($account?->status === ChannelAccountStatus::Connected) {
            return $account;
        }

        return null;
    }

    protected function hasConnectedInboxChannel(int $workspaceId): bool
    {
        return Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'website_widget')
            ->exists()
            || ChannelAccount::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('provider', $this->inboxProviders())
                ->where('status', ChannelAccountStatus::Connected->value)
                ->exists();
    }

    protected function counts(int $workspaceId, ?string $provider = null): array
    {
        $query = fn (): Builder => Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('provider', $this->inboxProviders())
            ->tap(fn (Builder $query) => $this->onlyConnectedInboxChannels($query))
            ->when($provider, fn (Builder $query) => $query->where('provider', $provider));

        return [
            'all' => $query()->count(),
            'open' => $query()->where('status', ConversationStatus::Open->value)->count(),
            'resolved' => $query()->where('status', ConversationStatus::Resolved->value)->count(),
        ];
    }

    protected function channelOptions(int $workspaceId): array
    {
        $connected = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('provider', $this->inboxProviders())
            ->where('status', ChannelAccountStatus::Connected->value)
            ->selectRaw('provider, count(*) as aggregate')
            ->groupBy('provider')
            ->pluck('aggregate', 'provider');
        $widgetConversationCount = Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'website_widget')
            ->count();
        $connectedCount = (int) $connected->sum() + $widgetConversationCount;

        $providers = collect($this->inboxProviders())
            ->map(fn (string $provider): array => [
                'value' => $provider,
                'label' => $this->providerLabel($provider),
                'icon' => $this->providerIcon($provider),
                'connected' => $provider === 'website_widget' ? $widgetConversationCount > 0 : ((int) ($connected[$provider] ?? 0)) > 0,
                'count' => $provider === 'website_widget' ? $widgetConversationCount : (int) ($connected[$provider] ?? 0),
            ])
            ->filter(fn (array $provider): bool => (bool) $provider['connected'])
            ->values();

        return collect([
            ['value' => 'all', 'label' => __('All channels'), 'icon' => 'ph-chats-circle', 'connected' => $connectedCount > 0, 'count' => $connectedCount],
            ...$providers->all(),
        ])->values()->all();
    }

    protected function formatConversation(Conversation $conversation, ?Message $latestMessage = null): array
    {
        $contact = $conversation->contact;
        $name = $contact?->name ?: $contact?->phone ?: __('Unknown contact');
        $labels = collect($conversation->labels ?? [])
            ->filter()
            ->values()
            ->all();

        if ($labels === [] && $contact?->relationLoaded('tags')) {
            $labels = $contact->tags->pluck('name')->take(2)->values()->all();
        }

        return [
            'id' => $conversation->id,
            'contact_id' => $contact?->id,
            'name' => $name,
            'initials' => $this->initials($name),
            'phone' => $contact?->phone,
            'email' => $contact?->email,
            'provider' => $conversation->provider,
            'provider_label' => $this->providerLabel((string) $conversation->provider),
            'provider_icon' => $this->providerIcon((string) $conversation->provider),
            'status' => $conversation->status?->value ?? (string) $conversation->status,
            'labels' => $labels,
            'channel_name' => $conversation->channelAccount?->name,
            'recipient_ready' => $this->recipientReady($conversation),
            'can_reply' => $this->canReply($conversation),
            'reply_disabled_reason' => $this->replyDisabledReason($conversation),
            'automated_reply_enabled' => $this->automatedReplyEnabled($conversation),
            'attachment_supported' => $this->supportsAttachments((string) $conversation->provider),
            'last_message' => $latestMessage ? $this->messageBody($latestMessage) : __('No messages yet.'),
            'last_message_direction' => $latestMessage?->direction,
            'last_message_status' => $latestMessage?->status?->value ?? $latestMessage?->status,
            'last_message_at' => optional($conversation->last_message_at ?: $latestMessage?->created_at)->toIso8601String(),
            'last_message_time' => $this->shortTime($conversation->last_message_at ?: $latestMessage?->created_at),
        ];
    }

    protected function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'type' => $message->type,
            'body' => $this->messageBody($message),
            'attachment' => $this->messageAttachment($message),
            'status' => $message->status?->value ?? (string) $message->status,
            'provider_message_id' => $message->provider_message_id,
            'created_at' => optional($message->created_at)->toIso8601String(),
            'time' => optional($message->created_at)->format('H:i'),
        ];
    }

    protected function messageBody(Message $message): string
    {
        if (filled($message->body)) {
            return (string) $message->body;
        }

        if ($message->type === 'template') {
            $templateName = data_get($message->payload, 'template_name')
                ?: data_get($message->payload, 'meta_payload.template.name');
            $templateBody = $this->templateMessageBody($message, $templateName);

            if (filled($templateBody)) {
                return $templateBody;
            }

            return filled($templateName)
                ? __('Template: :name', ['name' => $templateName])
                : __('WhatsApp template message');
        }

        return '';
    }

    protected function outboundPayload(string $body, ?array $attachment): array
    {
        if ($attachment === null) {
            return [
                'type' => 'text',
                'body' => $body,
            ];
        }

        return [
            'type' => $attachment['type'],
            'body' => $body,
            'caption' => $body,
            'url' => $attachment['url'],
            'filename' => $attachment['name'],
            'mime_type' => $attachment['mime_type'],
        ];
    }

    protected function threadsReplyTarget(Conversation $conversation): ?string
    {
        return Message::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('conversation_id', $conversation->id)
            ->where('provider', 'threads')
            ->where('direction', 'inbound')
            ->whereNotNull('provider_message_id')
            ->latest('id')
            ->value('provider_message_id');
    }

    protected function storeAttachment(int $workspaceId, UploadedFile $attachment): array
    {
        $path = $attachment->store("inbox/{$workspaceId}/".now()->format('Y/m'), 'public');
        $mimeType = (string) $attachment->getMimeType();
        $name = $attachment->getClientOriginalName();

        return [
            'disk' => 'public',
            'path' => $path,
            'url' => url(Storage::disk('public')->url($path)),
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => (int) $attachment->getSize(),
            'type' => $this->attachmentMessageType($mimeType),
        ];
    }

    protected function attachmentMessageType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }

    protected function messageAttachment(Message $message): ?array
    {
        $attachment = data_get($message->payload, 'attachment');

        return is_array($attachment) ? $attachment : null;
    }

    protected function recordFailedSend(
        int $workspaceId,
        Conversation $conversation,
        ?Contact $contact,
        ?ChannelAccount $account,
        string $provider,
        string $body,
        ?array $attachmentMeta,
        string $error,
    ): array {
        $message = Message::query()->create([
            'workspace_id' => $workspaceId,
            'channel_account_id' => $account?->id,
            'provider' => $provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact?->id,
            'direction' => 'outbound',
            'type' => $attachmentMeta['type'] ?? 'text',
            'body' => $body !== '' ? $body : ($attachmentMeta['name'] ?? null),
            'payload' => [
                'attachment' => $attachmentMeta,
                'response' => ['error' => ['message' => $error]],
            ],
            'status' => MessageStatus::Failed->value,
        ]);

        $conversation->forceFill([
            'channel_account_id' => $account?->id ?: $conversation->channel_account_id,
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
        ])->save();

        $contact?->updateQuietly(['last_interaction_at' => now()]);

        return [
            'ok' => false,
            'message' => $this->formatMessage($message),
            'conversation' => $this->formatConversation($conversation->fresh(['contact.tags', 'channelAccount']), $message),
            'error' => $error,
        ];
    }

    protected function recordWebsiteWidgetReply(int $workspaceId, Conversation $conversation, ?Contact $contact, string $body, ?array $attachmentMeta = null): array
    {
        if (! $contact) {
            return $this->recordFailedSend(
                $workspaceId,
                $conversation,
                null,
                null,
                'website_widget',
                $body,
                $attachmentMeta,
                __('This website chat no longer has a visitor contact.')
            );
        }

        $message = Message::query()->create([
            'workspace_id' => $workspaceId,
            'provider' => 'website_widget',
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => $attachmentMeta['type'] ?? 'text',
            'body' => $body !== '' ? $body : ($attachmentMeta['name'] ?? null),
            'payload' => [
                'source' => 'agent_reply',
                'attachment' => $attachmentMeta,
            ],
            'status' => MessageStatus::Sent->value,
        ]);

        $conversation->forceFill([
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
        ])->save();

        $contact->updateQuietly(['last_interaction_at' => now()]);

        return [
            'ok' => true,
            'message' => $this->formatMessage($message),
            'conversation' => $this->formatConversation($conversation->fresh(['contact.tags', 'channelAccount']), $message),
            'error' => null,
        ];
    }

    protected function templateMessageBody(Message $message, ?string $templateName): ?string
    {
        if ($message->provider !== 'whatsapp' || blank($templateName)) {
            return null;
        }

        $language = data_get($message->payload, 'language')
            ?: data_get($message->payload, 'meta_payload.template.language.code')
            ?: 'en_US';

        $template = MessageTemplate::query()
            ->where('workspace_id', $message->workspace_id)
            ->where('provider', 'whatsapp')
            ->where('name', $templateName)
            ->where('language', $language)
            ->first();

        if (! $template) {
            return null;
        }

        $contact = $message->contact;
        $variables = data_get($message->payload, 'variables', []);

        $body = collect($template->components ?? [])
            ->filter(fn (array $component): bool => in_array(strtoupper((string) ($component['type'] ?? '')), ['HEADER', 'BODY', 'FOOTER'], true))
            ->map(fn (array $component): string => (string) ($component['text'] ?? ''))
            ->filter()
            ->map(fn (string $text): string => $contact ? $this->variables->map($text, $contact, $variables) : $text)
            ->implode("\n\n");

        return filled($body) ? $body : null;
    }

    protected function providerContactId(Contact $contact, string $provider): ?string
    {
        $identity = $this->providerIdentity($contact, $provider);

        if ($identity?->provider_contact_id) {
            return (string) $identity->provider_contact_id;
        }

        if ($provider === 'whatsapp' && filled($contact->phone)) {
            return ltrim((string) $contact->phone, '+');
        }

        return null;
    }

    protected function recipientFor(?Contact $contact, string $provider): ?string
    {
        if (! $contact) {
            return null;
        }

        if ($provider === 'whatsapp') {
            return filled($contact->phone) ? (string) $contact->phone : $this->providerContactId($contact, $provider);
        }

        return $this->providerContactId($contact, $provider);
    }

    protected function providerIdentity(Contact $contact, string $provider): ?ContactProviderIdentity
    {
        if (! $contact->relationLoaded('identities')) {
            return $contact->identities()
                ->where('provider', $provider)
                ->orderByDesc('last_interaction_at')
                ->orderByDesc('id')
                ->first();
        }

        return $contact->identities
            ->where('provider', $provider)
            ->sortByDesc(fn (ContactProviderIdentity $identity): string => (string) ($identity->last_interaction_at?->timestamp ?? $identity->id))
            ->first();
    }

    protected function hasReplyChannel(int $workspaceId, string $provider): bool
    {
        if ($provider === 'website_widget') {
            return true;
        }

        return $this->connectedAccount($workspaceId, $provider) !== null;
    }

    protected function supportsAttachments(string $provider): bool
    {
        return in_array($provider, ['whatsapp', 'telegram', 'messenger', 'instagram', 'website_widget'], true);
    }

    protected function recipientReady(Conversation $conversation): bool
    {
        $contact = $conversation->contact;

        if (! $contact) {
            return false;
        }

        if ($conversation->provider === 'website_widget') {
            return true;
        }

        if ($conversation->provider === 'telegram') {
            return $this->providerIdentity($contact, 'telegram') !== null;
        }

        return filled($this->recipientFor($contact, (string) $conversation->provider));
    }

    protected function canReply(Conversation $conversation): bool
    {
        if ($conversation->provider === 'website_widget') {
            return ! $this->automatedReplyEnabled($conversation);
        }

        return $this->hasReplyChannel((int) $conversation->workspace_id, (string) $conversation->provider)
            && $this->recipientReady($conversation);
    }

    protected function replyDisabledReason(Conversation $conversation): ?string
    {
        if ($conversation->provider === 'website_widget' && $this->automatedReplyEnabled($conversation)) {
            return __('Automated chatbot replies are enabled for this widget. Turn them off before replying from Inbox.');
        }

        return null;
    }

    protected function automatedReplyEnabled(Conversation $conversation): bool
    {
        return (bool) $this->widgetForConversation($conversation)?->automatedReplyEnabled();
    }

    protected function widgetForConversation(Conversation $conversation): ?ChatbotWidget
    {
        if ($conversation->provider !== 'website_widget') {
            return null;
        }

        return ChatbotWidgetSession::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('conversation_id', $conversation->id)
            ->with('widget')
            ->latest('id')
            ->first()
            ?->widget;
    }

    protected function telegramOptInPayload(Conversation $conversation): ?array
    {
        if ($conversation->provider !== 'telegram' || ! $conversation->contact) {
            return null;
        }

        if ($this->providerIdentity($conversation->contact, 'telegram')) {
            return null;
        }

        return $this->telegramOptIns->linkFor($conversation->contact);
    }

    protected function providerFilter(mixed $provider): ?string
    {
        $provider = is_string($provider) ? strtolower($provider) : null;

        return in_array($provider, $this->inboxProviders(), true) ? $provider : null;
    }

    protected function providerLabel(string $provider): string
    {
        return __(config("marketing-channels.providers.{$provider}.label", ucfirst($provider)));
    }

    protected function providerIcon(string $provider): string
    {
        return config("marketing-channels.providers.{$provider}.icon", 'ph-chats-circle');
    }

    /**
     * @return array<int, string>
     */
    protected function inboxProviders(): array
    {
        return collect(config('marketing-channels.providers', []))
            ->filter(fn (array $provider): bool => (bool) ($provider['inbox'] ?? false))
            ->keys()
            ->values()
            ->all();
    }

    protected function initials(string $name): string
    {
        $parts = Str::of($name)->squish()->explode(' ')->filter()->values();

        return $parts->take(2)->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: 'WA';
    }

    protected function shortTime(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        return $date->isToday() ? $date->format('H:i') : $date->diffForHumans(null, true, true);
    }
}
