<?php

namespace App\Modules\Chatbots\Services;

use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Chatbots\Models\ChatbotWidgetSession;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatbotWidgetService
{
    public const PROVIDER = 'website_widget';

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ClaudeReplyService $replies,
        protected SubscriptionAccessService $subscriptions,
    ) {}

    public function listForUser(?User $user): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);

        return ChatbotWidget::query()
            ->where('workspace_id', $workspace->id)
            ->with('chatbot')
            ->withCount('sessions')
            ->latest()
            ->paginate(12);
    }

    public function create(?User $user, array $data): ChatbotWidget
    {
        $workspace = $this->workspaces->current($user);

        return ChatbotWidget::query()->create([
            'workspace_id' => $workspace->id,
            'public_token' => Str::random(48),
            ...$this->payload($workspace->id, $data),
        ]);
    }

    public function update(?User $user, ChatbotWidget $widget, array $data): ChatbotWidget
    {
        $widget = $this->forUser($user, $widget);
        $widget->update($this->payload((int) $widget->workspace_id, $data));

        return $widget->fresh('chatbot');
    }

    public function delete(?User $user, ChatbotWidget $widget): void
    {
        $this->forUser($user, $widget)->delete();
    }

    public function forUser(?User $user, ChatbotWidget $widget): ChatbotWidget
    {
        $workspace = $this->workspaces->current($user);

        abort_unless((int) $widget->workspace_id === (int) $workspace->id, 404);

        return $widget;
    }

    public function publicWidget(string $token, Request $request): ChatbotWidget
    {
        $widget = ChatbotWidget::query()
            ->where('public_token', $token)
            ->with('chatbot.knowledgeBases')
            ->firstOrFail();

        abort_unless($widget->is_active, 404);
        $this->subscriptions->assertActiveForUse((int) $widget->workspace_id);

        if (! $this->domainAllowed($widget, $request)) {
            abort(403, 'This website is not allowed to use this chatbot widget.');
        }

        return $widget;
    }

    public function startSession(ChatbotWidget $widget, Request $request, array $data): ChatbotWidgetSession
    {
        $visitorUid = $data['visitor_uid'] ?? null;
        $sessionToken = $data['session_token'] ?? null;

        $session = ChatbotWidgetSession::query()
            ->where('widget_id', $widget->id)
            ->when($sessionToken, fn ($query) => $query->where('session_token', $sessionToken))
            ->when(! $sessionToken && $visitorUid, fn ($query) => $query->where('visitor_uid', $visitorUid))
            ->first();

        if (! $session) {
            $contact = $this->contactFor($widget, $data);
            $conversation = $this->conversationFor($widget, $contact);

            $session = ChatbotWidgetSession::query()->create([
                'workspace_id' => $widget->workspace_id,
                'widget_id' => $widget->id,
                'chatbot_id' => $widget->chatbot_id,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'session_token' => Str::random(64),
                'visitor_uid' => $visitorUid ?: Str::uuid()->toString(),
                'visitor_metadata' => $this->visitorMetadata($data),
                'ip_hash' => $this->hashNullable($request->ip()),
                'user_agent_hash' => $this->hashNullable($request->userAgent()),
                'last_seen_at' => now(),
            ]);
        } else {
            $this->refreshSession($session, $request, $data);
        }

        return $session->fresh(['conversation', 'contact', 'widget.chatbot']);
    }

    public function receiveMessage(ChatbotWidget $widget, ChatbotWidgetSession $session, string $body, ?UploadedFile $attachment = null): array
    {
        $this->assertSession($widget, $session);

        $body = trim($body);
        $attachmentMeta = $attachment ? $this->storeAttachment((int) $widget->workspace_id, $attachment) : null;
        $conversation = $session->conversation;
        $contact = $session->contact;

        if (! $conversation || ! $contact) {
            throw ValidationException::withMessages([
                'session' => 'Start a widget session before sending a message.',
            ]);
        }

        $inbound = Message::query()->create([
            'workspace_id' => $widget->workspace_id,
            'provider' => self::PROVIDER,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'type' => $attachmentMeta['type'] ?? 'text',
            'body' => $body !== '' ? $body : ($attachmentMeta['name'] ?? null),
            'payload' => [
                'widget_id' => $widget->id,
                'session_id' => $session->id,
                'attachment' => $attachmentMeta,
            ],
            'status' => MessageStatus::Received->value,
        ]);

        $conversation->forceFill([
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
        ])->save();
        $contact->updateQuietly(['last_interaction_at' => now()]);
        $session->update(['last_seen_at' => now()]);

        if (! $widget->automatedReplyEnabled()) {
            return [
                'message' => $this->formatMessage($inbound),
                'reply' => null,
                'error' => 'Thanks. A team member will reply here soon.',
            ];
        }

        $chatbot = $widget->chatbot;

        if ($attachmentMeta && $body === '') {
            return [
                'message' => $this->formatMessage($inbound),
                'reply' => null,
                'error' => 'Thanks. A team member will review your file and reply here soon.',
            ];
        }

        if (! $chatbot?->is_active) {
            return [
                'message' => $this->formatMessage($inbound),
                'reply' => null,
                'error' => 'Thanks. A team member will reply here soon.',
            ];
        }

        $draft = $this->replies->draftReply($body, [
            'chatbot' => $chatbot,
            'widget_id' => $widget->id,
            'widget_session_id' => $session->id,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
        ]);

        $reply = Message::query()->create([
            'workspace_id' => $widget->workspace_id,
            'provider' => self::PROVIDER,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => (string) ($draft['reply'] ?? ''),
            'payload' => [
                'widget_id' => $widget->id,
                'session_id' => $session->id,
                'chatbot_id' => $chatbot->id,
                'chatbot_reply' => [
                    'provider' => $draft['provider'] ?? null,
                    'model' => $draft['model'] ?? null,
                    'confidence' => $draft['confidence'] ?? null,
                    'handoff' => $draft['handoff'] ?? false,
                    'knowledge_context_count' => data_get($draft, 'context.knowledge_context_count', 0),
                    'search_mode' => data_get($draft, 'context.search_mode'),
                    'sources_used' => data_get($draft, 'context.sources_used', []),
                ],
            ],
            'status' => MessageStatus::Sent->value,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return [
            'message' => $this->formatMessage($inbound),
            'reply' => $this->formatMessage($reply),
            'debug' => [
                'provider' => $draft['provider'] ?? null,
                'model' => $draft['model'] ?? null,
                'handoff' => $draft['handoff'] ?? false,
                'search_mode' => data_get($draft, 'context.search_mode'),
                'knowledge_context_count' => data_get($draft, 'context.knowledge_context_count', 0),
                'sources_used' => data_get($draft, 'context.sources_used', []),
            ],
        ];
    }

    public function messages(ChatbotWidget $widget, ChatbotWidgetSession $session, ?int $afterId = null): Collection
    {
        $this->assertSession($widget, $session);

        return Message::query()
            ->where('workspace_id', $widget->workspace_id)
            ->where('conversation_id', $session->conversation_id)
            ->when($afterId, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message): array => $this->formatMessage($message));
    }

    public function publicConfig(ChatbotWidget $widget): array
    {
        return [
            'name' => $widget->name,
            'greeting' => $widget->greeting ?: $widget->chatbot?->greeting,
            'lead_fields' => $widget->lead_fields ?? [],
            'settings' => [
                'primary_color' => $widget->setting('primary_color', '#16a34a'),
                'position' => $widget->setting('position', 'right'),
                'launcher_label' => $widget->setting('launcher_label', 'Chat'),
                'automated_reply_enabled' => $widget->automatedReplyEnabled(),
            ],
        ];
    }

    protected function payload(int $workspaceId, array $data): array
    {
        $chatbotId = (int) $data['chatbot_id'];
        $chatbot = Chatbot::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($chatbotId)
            ->firstOrFail();

        return [
            'chatbot_id' => $chatbot->id,
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'allowed_domains' => $this->lines($data['allowed_domains'] ?? ''),
            'lead_fields' => array_values($data['lead_fields'] ?? []),
            'greeting' => filled($data['greeting'] ?? null) ? $data['greeting'] : null,
            'settings' => [
                'primary_color' => $data['primary_color'] ?? '#16a34a',
                'position' => $data['position'] ?? 'right',
                'launcher_label' => $data['launcher_label'] ?? 'Chat',
                'automated_reply_enabled' => (bool) ($data['automated_reply_enabled'] ?? false),
            ],
        ];
    }

    protected function contactFor(ChatbotWidget $widget, array $data): Contact
    {
        $email = filled($data['email'] ?? null) ? strtolower((string) $data['email']) : null;
        $phone = filled($data['phone'] ?? null) ? preg_replace('/\s+/', '', (string) $data['phone']) : null;

        $contact = Contact::query()
            ->where('workspace_id', $widget->workspace_id)
            ->when($phone || $email, function ($query) use ($phone, $email): void {
                $query->where(function ($query) use ($phone, $email): void {
                    $query->when($phone, fn ($query) => $query->orWhere('phone', $phone))
                        ->when($email, fn ($query) => $query->orWhere('email', $email));
                });
            })
            ->when(! $phone && ! $email, fn ($query) => $query->whereRaw('1 = 0'))
            ->first();

        $payload = [
            'name' => filled($data['name'] ?? null) ? $data['name'] : 'Website Visitor',
            'email' => $email,
            'phone' => $phone,
            'source' => ContactSource::Website->value,
            'opt_in_status' => ContactOptInStatus::Unknown->value,
            'last_interaction_at' => now(),
            'custom_fields' => [
                'chatbot_widget_id' => $widget->id,
                'chatbot_widget_name' => $widget->name,
            ],
        ];

        if ($contact) {
            $contact->fill(array_filter($payload, fn (mixed $value): bool => $value !== null))->save();

            return $contact;
        }

        return Contact::query()->create([
            'workspace_id' => $widget->workspace_id,
            ...$payload,
        ]);
    }

    protected function conversationFor(ChatbotWidget $widget, Contact $contact): Conversation
    {
        return Conversation::query()->create([
            'workspace_id' => $widget->workspace_id,
            'provider' => self::PROVIDER,
            'provider_conversation_id' => 'widget-'.$widget->id.'-'.$contact->id.'-'.Str::random(8),
            'contact_id' => $contact->id,
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
            'labels' => ['Website widget'],
        ]);
    }

    protected function refreshSession(ChatbotWidgetSession $session, Request $request, array $data): void
    {
        $metadata = array_filter(array_merge($session->visitor_metadata ?? [], $this->visitorMetadata($data)));

        $session->update([
            'visitor_metadata' => $metadata,
            'ip_hash' => $this->hashNullable($request->ip()),
            'user_agent_hash' => $this->hashNullable($request->userAgent()),
            'last_seen_at' => now(),
        ]);
    }

    protected function assertSession(ChatbotWidget $widget, ChatbotWidgetSession $session): void
    {
        abort_unless(
            (int) $session->widget_id === (int) $widget->id
            && (int) $session->workspace_id === (int) $widget->workspace_id,
            404
        );
    }

    protected function domainAllowed(ChatbotWidget $widget, Request $request): bool
    {
        $host = $this->requestHost($request);

        if (! $host || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        $allowed = collect($widget->allowed_domains ?? [])
            ->map(fn (string $domain): string => Str::lower(trim($domain)))
            ->filter()
            ->values();

        if ($allowed->isEmpty()) {
            return false;
        }

        return $allowed->contains(fn (string $domain): bool => $host === $domain || Str::endsWith($host, '.'.$domain));
    }

    protected function requestHost(Request $request): ?string
    {
        $origin = $request->headers->get('origin') ?: $request->headers->get('referer');

        if (! $origin) {
            return null;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        return is_string($host) ? Str::lower($host) : null;
    }

    protected function visitorMetadata(array $data): array
    {
        return array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'page_url' => $data['page_url'] ?? null,
            'timezone' => $data['timezone'] ?? null,
        ], fn (mixed $value): bool => filled($value));
    }

    protected function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'type' => $message->type,
            'body' => $message->body,
            'attachment' => $this->messageAttachment($message),
            'status' => $message->status?->value ?? (string) $message->status,
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }

    protected function storeAttachment(int $workspaceId, UploadedFile $attachment): array
    {
        $path = $attachment->store("inbox/{$workspaceId}/".now()->format('Y/m'), 'public');
        $mimeType = (string) $attachment->getMimeType();

        return [
            'disk' => 'public',
            'path' => $path,
            'url' => url(Storage::disk('public')->url($path)),
            'name' => $attachment->getClientOriginalName(),
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

    protected function lines(string|array|null $value): array
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($line): string => (string) $line)->filter()->values()->all();
        }

        return Str::of((string) $value)
            ->replace(["\r\n", "\r"], "\n")
            ->explode("\n")
            ->map(fn (string $line): string => Str::lower(trim($line)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function hashNullable(?string $value): ?string
    {
        return filled($value) ? hash('sha256', (string) $value) : null;
    }
}
