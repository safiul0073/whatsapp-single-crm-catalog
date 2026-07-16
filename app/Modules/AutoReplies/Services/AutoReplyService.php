<?php

namespace App\Modules\AutoReplies\Services;

use App\Models\User;
use App\Modules\AutoReplies\Models\AutoReplyRule;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AutoReplyService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ChannelManager $channels,
    ) {}

    public function listForUser(?User $user): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);

        return AutoReplyRule::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('priority')
            ->latest()
            ->paginate(12);
    }

    public function create(?User $user, array $data): AutoReplyRule
    {
        $workspace = $this->workspaces->current($user);

        return AutoReplyRule::query()->create([
            'workspace_id' => $workspace->id,
            ...$this->payload($data),
        ]);
    }

    public function update(?User $user, AutoReplyRule $rule, array $data): AutoReplyRule
    {
        $rule = $this->forUser($user, $rule);
        $rule->update($this->payload($data));

        return $rule->fresh();
    }

    public function toggle(?User $user, AutoReplyRule $rule): AutoReplyRule
    {
        $rule = $this->forUser($user, $rule);
        $rule->update(['is_active' => ! $rule->is_active]);

        return $rule->fresh();
    }

    public function delete(?User $user, AutoReplyRule $rule): void
    {
        $this->forUser($user, $rule)->delete();
    }

    public function forUser(?User $user, AutoReplyRule $rule): AutoReplyRule
    {
        $workspace = $this->workspaces->current($user);

        abort_unless($rule->workspace_id === $workspace->id, 404);

        return $rule;
    }

    protected function payload(array $data): array
    {
        $triggerType = $data['trigger_type'];
        $replyType = $data['reply_type'];
        $replyText = trim((string) ($data['reply_text'] ?? ''));
        $replyPayload = $this->replyPayload($replyType, $replyText, $data);

        return [
            'name' => $data['name'],
            'trigger_type' => $triggerType,
            'trigger_value' => $triggerType === 'keyword' ? $data['trigger_value'] : null,
            'match_type' => $triggerType === 'keyword' ? $data['match_type'] : 'contains',
            'reply_type' => $replyType,
            'reply_text' => $replyText,
            'reply_payload' => $replyPayload,
            'priority' => (int) ($data['priority'] ?? 10),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    public function replyToInbound(ChannelAccount $account, Conversation $conversation, Message $inboundMessage): ?Message
    {
        if (! $inboundMessage->wasRecentlyCreated || $inboundMessage->direction !== 'inbound') {
            return null;
        }

        $rule = $this->matchingRule($account, $conversation, $inboundMessage);

        if (! $rule) {
            return null;
        }

        $payload = $this->sendPayload($rule, $account, $conversation, $inboundMessage);

        if ($payload === null) {
            return null;
        }

        $recipient = $this->recipientFor($account, $conversation, $inboundMessage);

        if (blank($recipient)) {
            return $this->recordOutbound($account, $conversation, $rule, $payload, [
                'ok' => false,
                'status' => MessageStatus::Failed->value,
                'response' => ['error' => ['message' => 'Auto-reply recipient is missing.']],
            ]);
        }

        try {
            $result = $this->channels->sendMessage($account, [
                'to' => $recipient,
                'phone' => $recipient,
                'provider_contact_id' => $recipient,
            ], $payload);
        } catch (\Throwable $exception) {
            $result = [
                'ok' => false,
                'status' => MessageStatus::Failed->value,
                'response' => ['error' => ['message' => $exception->getMessage()]],
            ];
        }

        return $this->recordOutbound($account, $conversation, $rule, $payload, $result);
    }

    protected function matchingRule(ChannelAccount $account, Conversation $conversation, Message $message): ?AutoReplyRule
    {
        $body = (string) $message->body;
        $rules = AutoReplyRule::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderByDesc('id')
            ->get();

        return $rules->first(function (AutoReplyRule $rule) use ($account, $conversation, $message, $body): bool {
            if (! $this->ruleCanSendOnAccount($rule, $account)) {
                return false;
            }

            return match ($rule->trigger_type) {
                'keyword' => $this->keywordMatches($rule, $body),
                'welcome' => $this->isFirstInboundMessage($conversation, $message),
                'out_of_hours' => $this->isOutsideBusinessHours($conversation),
                default => false,
            };
        }) ?: $rules->first(fn (AutoReplyRule $rule): bool => $rule->trigger_type === 'fallback' && $this->ruleCanSendOnAccount($rule, $account));
    }

    protected function ruleCanSendOnAccount(AutoReplyRule $rule, ChannelAccount $account): bool
    {
        return $rule->reply_type !== 'template' || $account->provider === 'whatsapp';
    }

    protected function keywordMatches(AutoReplyRule $rule, string $body): bool
    {
        if (blank($body) || blank($rule->trigger_value)) {
            return false;
        }

        $keywords = collect(explode(',', (string) $rule->trigger_value))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->values();

        if ($keywords->isEmpty()) {
            return false;
        }

        return $keywords->contains(function (string $keyword) use ($rule, $body): bool {
            return match ($rule->match_type) {
                'exact' => Str::lower(trim($body)) === Str::lower($keyword),
                'regex' => $this->regexMatches($keyword, $body),
                default => Str::contains(Str::lower($body), Str::lower($keyword)),
            };
        });
    }

    protected function regexMatches(string $pattern, string $body): bool
    {
        $expression = str_starts_with($pattern, '/') ? $pattern : '/'.$pattern.'/i';

        return rescue(fn (): bool => preg_match($expression, $body) === 1, false, report: false);
    }

    protected function isFirstInboundMessage(Conversation $conversation, Message $message): bool
    {
        return Message::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->whereKeyNot($message->id)
            ->doesntExist();
    }

    protected function isOutsideBusinessHours(Conversation $conversation): bool
    {
        $timezone = $conversation->channelAccount?->workspace?->timezone ?? config('app.timezone', 'UTC');
        $hour = (int) now($timezone)->format('G');

        return $hour < 9 || $hour >= 17;
    }

    protected function replyPayload(string $replyType, string $replyText, array $data): array
    {
        if ($replyType === 'template') {
            $template = MessageTemplate::query()->find($data['message_template_id'] ?? null);

            return [
                'text' => $replyText,
                'message_template_id' => $template?->id,
                'template_name' => $template?->name,
                'language' => $template?->language ?? 'en_US',
                'components' => $data['components'] ?? [],
            ];
        }

        if ($replyType === 'media') {
            return $this->mediaPayload($replyText, $data);
        }

        return ['text' => $replyText];
    }

    protected function mediaPayload(string $replyText, array $data): array
    {
        $media = filled($data['media_id'] ?? null) ? Media::query()->find($data['media_id']) : null;
        $type = $media ? $this->messageTypeForMediaType((string) $media->type) : ($data['media_type'] ?? 'document');
        $url = $media?->url ?: ($data['media_url'] ?? null);
        $caption = trim((string) ($data['media_caption'] ?? $replyText));

        return [
            'text' => $replyText,
            'media_id' => $media?->id,
            'media_source' => $media ? 'library' : 'url',
            'media_url' => $url,
            'url' => $url,
            'type' => $this->messageTypeForMediaType((string) $type),
            'caption' => $caption,
            'filename' => $media?->original_name ?: basename((string) parse_url((string) $url, PHP_URL_PATH)),
            'mime_type' => $media?->mime_type,
        ];
    }

    protected function sendPayload(AutoReplyRule $rule, ChannelAccount $account, Conversation $conversation, Message $message): ?array
    {
        $payload = $rule->reply_payload ?? [];

        if ($rule->reply_type === 'template') {
            if ($account->provider !== 'whatsapp') {
                return null;
            }

            $template = MessageTemplate::query()
                ->where('workspace_id', $account->workspace_id)
                ->where('provider', 'whatsapp')
                ->where('status', MessageTemplateStatus::Approved->value)
                ->find($payload['message_template_id'] ?? null);

            if (! $template) {
                return null;
            }

            return [
                'type' => 'template',
                'template_name' => $template->name,
                'language' => $template->language ?? 'en_US',
                'components' => $payload['components'] ?? [],
                'body' => $rule->reply_text ?: null,
            ];
        }

        if ($rule->reply_type === 'media') {
            if (blank($payload['url'] ?? $payload['media_url'] ?? null)) {
                return null;
            }

            return [
                'type' => $this->messageTypeForMediaType((string) ($payload['type'] ?? 'document')),
                'body' => $payload['caption'] ?? $rule->reply_text ?? '',
                'caption' => $payload['caption'] ?? $rule->reply_text ?? '',
                'url' => $payload['url'] ?? $payload['media_url'],
                'filename' => $payload['filename'] ?? null,
                'mime_type' => $payload['mime_type'] ?? null,
            ];
        }

        if (blank($rule->reply_text)) {
            return null;
        }

        return [
            'type' => 'text',
            'body' => $rule->reply_text,
        ];
    }

    protected function recipientFor(ChannelAccount $account, Conversation $conversation, Message $message): ?string
    {
        $providerContactId = data_get($message->payload, 'from')
            ?: data_get($message->payload, 'sender.id')
            ?: $conversation->provider_conversation_id;

        if ($account->provider === 'whatsapp') {
            return preg_replace('/\D+/', '', (string) $providerContactId);
        }

        return filled($providerContactId) ? (string) $providerContactId : null;
    }

    protected function recordOutbound(ChannelAccount $account, Conversation $conversation, AutoReplyRule $rule, array $payload, array $result): Message
    {
        $message = Message::query()->create([
            'workspace_id' => $account->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => $account->provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => 'outbound',
            'type' => $payload['type'] ?? 'text',
            'body' => $payload['body'] ?? $rule->reply_text,
            'payload' => array_merge($payload, [
                'source' => 'auto_reply',
                'auto_reply_rule_id' => $rule->id,
                'response' => $result['response'] ?? null,
            ]),
            'status' => ($result['status'] ?? null) ?: (($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value),
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $account->provider === 'whatsapp' ? ($result['provider_message_id'] ?? null) : null,
        ]);

        $conversation->forceFill([
            'channel_account_id' => $account->id,
            'status' => ConversationStatus::Open->value,
            'last_message_at' => now(),
        ])->save();

        return $message;
    }

    protected function messageTypeForMediaType(string $type): string
    {
        return match ($type) {
            'image', 'video', 'audio' => $type,
            default => 'document',
        };
    }
}
