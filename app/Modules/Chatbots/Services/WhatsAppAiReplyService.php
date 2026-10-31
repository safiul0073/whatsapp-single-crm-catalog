<?php

namespace App\Modules\Chatbots\Services;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Workspaces\Models\Workspace;

class WhatsAppAiReplyService
{
    public function __construct(
        protected ClaudeReplyService $replies,
        protected ChannelManager $channels,
    ) {}

    /** @return array{enabled: bool, chatbot_id: int|null} */
    public function settings(Workspace $workspace): array
    {
        return [
            'enabled' => (bool) data_get($workspace->settings, 'whatsapp_ai.enabled', false),
            'chatbot_id' => data_get($workspace->settings, 'whatsapp_ai.chatbot_id'),
        ];
    }

    public function enabledFor(int $workspaceId): bool
    {
        $workspace = Workspace::query()->find($workspaceId);

        return $workspace !== null && $this->settings($workspace)['enabled'];
    }

    public function updateSettings(Workspace $workspace, bool $enabled, ?int $chatbotId): void
    {
        $settings = $workspace->settings ?? [];
        $settings['whatsapp_ai'] = ['enabled' => $enabled, 'chatbot_id' => $chatbotId];
        $workspace->update(['settings' => $settings]);
    }

    public function reply(Message $inbound): ?Message
    {
        $inbound->loadMissing(['conversation.contact', 'conversation.channelAccount']);
        $conversation = $inbound->conversation;
        $account = $conversation?->channelAccount;
        $body = trim((string) $inbound->body);

        if (! $conversation || ! $account || $account->provider !== 'whatsapp' || $inbound->direction !== 'inbound' || $body === '') {
            return null;
        }

        $workspace = Workspace::query()->find($account->workspace_id);
        $settings = $workspace ? $this->settings($workspace) : ['enabled' => false, 'chatbot_id' => null];

        // Human-handled conversations and expired 24h windows are never auto-answered.
        if (! $settings['enabled'] || $conversation->assigned_to !== null
            || ($conversation->session_expires_at && $conversation->session_expires_at->isPast())) {
            return null;
        }

        $chatbot = Chatbot::query()->where('workspace_id', $account->workspace_id)->where('is_active', true)->find($settings['chatbot_id']);

        if (! $chatbot) {
            return null;
        }

        $draft = $this->replies->draftReply($body, [
            'chatbot' => $chatbot,
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
        ]);

        // Low-confidence / knowledge-base misses are left for a human agent.
        if (($draft['handoff'] ?? false) || blank($draft['reply'] ?? null)) {
            return null;
        }

        $recipient = $inbound->contact?->phone ?? $conversation->contact?->phone;
        $payload = ['type' => 'text', 'body' => (string) $draft['reply']];

        try {
            $result = $this->channels->sendMessage($account, [
                'to' => $recipient,
                'phone' => $recipient,
                'provider_contact_id' => $recipient,
            ], $payload);
        } catch (\Throwable $exception) {
            $result = ['ok' => false, 'status' => MessageStatus::Failed->value, 'response' => ['error' => ['message' => $exception->getMessage()]]];
        }

        return Message::query()->create([
            'workspace_id' => $account->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => $account->provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $payload['body'],
            'payload' => array_merge($payload, ['source' => 'ai_chatbot', 'chatbot_id' => $chatbot->id, 'response' => $result['response'] ?? null]),
            'status' => ($result['status'] ?? null) ?: (($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value),
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $result['provider_message_id'] ?? null,
        ]);
    }
}
