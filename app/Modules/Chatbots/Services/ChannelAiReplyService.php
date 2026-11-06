<?php

namespace App\Modules\Chatbots\Services;

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Workspaces\Models\Workspace;
use Throwable;

/**
 * Answers inbound WhatsApp, Messenger and Instagram messages with the workspace's AI chatbot,
 * then sends the color photos of the products the assistant showed.
 */
class ChannelAiReplyService
{
    public const PROVIDERS = ['whatsapp', 'messenger', 'instagram'];

    public const MAX_IMAGES_PER_REPLY = 8;

    public function __construct(
        protected ClaudeReplyService $replies,
        protected ChannelManager $channels,
        protected HumanHandoffService $handoff,
    ) {}

    /**
     * @return array<string, array{enabled: bool, chatbot_id: int|null}>
     */
    public function settings(Workspace $workspace): array
    {
        return collect(self::PROVIDERS)
            ->mapWithKeys(fn (string $provider): array => [$provider => $this->channelSettings($workspace, $provider)])
            ->all();
    }

    /**
     * @return array{enabled: bool, chatbot_id: int|null}
     */
    public function channelSettings(Workspace $workspace, string $provider): array
    {
        $stored = data_get($workspace->settings, "channel_ai.{$provider}");
        if (! is_array($stored) && $provider === 'whatsapp') {
            $stored = data_get($workspace->settings, 'whatsapp_ai');
        }

        $chatbotId = data_get($stored, 'chatbot_id');

        return [
            'enabled' => (bool) data_get($stored, 'enabled', false),
            'chatbot_id' => filled($chatbotId) ? (int) $chatbotId : null,
        ];
    }

    public function enabledFor(int $workspaceId, string $provider): bool
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return false;
        }

        $workspace = Workspace::query()->find($workspaceId);

        return $workspace !== null && $this->channelSettings($workspace, $provider)['enabled'];
    }

    /**
     * @param  array<string, array{enabled?: mixed, chatbot_id?: mixed}>  $channels
     */
    public function updateSettings(Workspace $workspace, array $channels): void
    {
        $settings = $workspace->settings ?? [];

        foreach (self::PROVIDERS as $provider) {
            if (! array_key_exists($provider, $channels)) {
                continue;
            }
            $chatbotId = $channels[$provider]['chatbot_id'] ?? null;
            $settings['channel_ai'][$provider] = [
                'enabled' => (bool) ($channels[$provider]['enabled'] ?? false),
                'chatbot_id' => filled($chatbotId) ? (int) $chatbotId : null,
            ];
        }
        unset($settings['whatsapp_ai']);

        $workspace->update(['settings' => $settings]);
    }

    public function reply(Message $inbound): ?Message
    {
        $inbound->loadMissing(['conversation.contact', 'conversation.channelAccount']);
        $conversation = $inbound->conversation;
        $account = $conversation?->channelAccount;
        $body = trim((string) $inbound->body);

        if (! $conversation || ! $account || ! in_array($account->provider, self::PROVIDERS, true) || $inbound->direction !== 'inbound' || $body === '') {
            return null;
        }

        $workspace = Workspace::query()->find($account->workspace_id);
        $settings = $workspace ? $this->channelSettings($workspace, $account->provider) : ['enabled' => false, 'chatbot_id' => null];

        // Human-handled conversations and expired WhatsApp 24h windows are never auto-answered.
        if (! $settings['enabled'] || $conversation->assigned_to !== null || $conversation->isAiPaused()
            || ($account->provider === 'whatsapp' && $conversation->session_expires_at && $conversation->session_expires_at->isPast())) {
            return null;
        }

        $chatbot = Chatbot::query()->where('workspace_id', $account->workspace_id)->where('is_active', true)->find($settings['chatbot_id']);
        $recipient = $this->recipient($account, $conversation, $inbound);

        if (! $chatbot || blank($recipient)) {
            return null;
        }

        $draft = $this->replies->draftReply($body, [
            'chatbot' => $chatbot,
            'channel' => $account->provider,
            'conversation_id' => $conversation->id,
            'inbound_message_id' => $inbound->id,
            'contact_id' => $conversation->contact_id,
        ]);

        if (blank($draft['reply'] ?? null)) {
            return null;
        }

        if ($draft['handoff'] ?? false) {
            $reason = $draft['handoff_reason'] ?? null;

            // Technical AI failures stay silent so a team member can answer; they never switch on Human takeover.
            if (! in_array($reason, HumanHandoffService::PAUSING_REASONS, true)) {
                return null;
            }

            $reply = $this->send($account, $conversation, $recipient, $chatbot, ['type' => 'text', 'body' => (string) $draft['reply']]);
            if ($this->handoff->shouldHandOff($chatbot, $reason)) {
                $this->handoff->handOff($conversation, $chatbot, $reason, $body);
            }

            return $reply;
        }

        $reply = $this->send($account, $conversation, $recipient, $chatbot, ['type' => 'text', 'body' => (string) $draft['reply']]);

        if ($reply->status !== MessageStatus::Failed) {
            $this->sendProductImages($account, $conversation, $recipient, $chatbot, $draft['products'] ?? []);
        }

        return $reply;
    }

    /**
     * @param  list<array{name: string, url: string, colors: list<array{color: string, image_url: string, price_line: ?string}>, more_colors: int}>  $products
     */
    protected function sendProductImages(ChannelAccount $account, Conversation $conversation, string $recipient, Chatbot $chatbot, array $products): void
    {
        $sent = 0;

        foreach ($products as $product) {
            $lines = [];
            foreach ($product['colors'] as $color) {
                if ($sent >= self::MAX_IMAGES_PER_REPLY) {
                    break 2;
                }
                $caption = trim($product['name'].($color['color'] !== '' ? ' - '.$color['color'] : '').($color['price_line'] ? "\n".$color['price_line'] : ''));
                $payload = ['type' => 'image', 'url' => $color['image_url'], 'product_url' => $product['url']];
                if ($account->provider === 'whatsapp') {
                    $payload['caption'] = $caption;
                }
                $this->send($account, $conversation, $recipient, $chatbot, $payload, $caption);
                $lines[] = $caption;
                $sent++;
            }

            $more = (int) ($product['more_colors'] ?? 0);
            $footer = array_filter([
                // Messenger and Instagram images have no caption, so the color names follow as text.
                $account->provider !== 'whatsapp' && $lines !== [] ? implode("\n", array_map(fn (string $line): string => '• '.str_replace("\n", ' | ', $line), $lines)) : null,
                $more > 0 ? "+{$more} more ".($more === 1 ? 'color' : 'colors').': '.$product['url'] : null,
                $account->provider !== 'whatsapp' && $more === 0 ? $product['url'] : null,
            ]);
            if ($footer !== []) {
                $this->send($account, $conversation, $recipient, $chatbot, ['type' => 'text', 'body' => implode("\n", $footer)]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function send(ChannelAccount $account, Conversation $conversation, string $recipient, Chatbot $chatbot, array $payload, ?string $body = null): Message
    {
        try {
            $result = $this->channels->sendMessage($account, [
                'to' => $recipient,
                'phone' => $recipient,
                'id' => $recipient,
                'provider_contact_id' => $recipient,
            ], $payload);
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'status' => MessageStatus::Failed->value, 'response' => ['error' => ['message' => $exception->getMessage()]]];
        }

        $isImage = ($payload['type'] ?? 'text') === 'image';

        return Message::query()->create([
            'workspace_id' => $account->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => $account->provider,
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => 'outbound',
            'type' => $isImage ? 'image' : 'text',
            'body' => $body ?? ($payload['body'] ?? null),
            'payload' => array_merge($payload, [
                'source' => 'ai_chatbot',
                'chatbot_id' => $chatbot->id,
                'attachment' => $isImage ? ['url' => $payload['url'], 'type' => 'image', 'name' => $body, 'mime_type' => 'image/jpeg'] : null,
                'response' => $result['response'] ?? null,
            ]),
            'status' => ($result['status'] ?? null) ?: (($result['ok'] ?? false) ? MessageStatus::Sent->value : MessageStatus::Failed->value),
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'whatsapp_message_id' => $account->provider === 'whatsapp' ? ($result['provider_message_id'] ?? null) : null,
        ]);
    }

    protected function recipient(ChannelAccount $account, Conversation $conversation, Message $inbound): ?string
    {
        $contact = $inbound->contact ?? $conversation->contact;

        if ($account->provider === 'whatsapp' && filled($contact?->phone)) {
            return (string) $contact->phone;
        }

        $identity = $contact
            ? ContactProviderIdentity::query()
                ->where('contact_id', $contact->id)
                ->where('provider', $account->provider)
                ->orderByDesc('last_interaction_at')
                ->orderByDesc('id')
                ->value('provider_contact_id')
            : null;

        return filled($identity) ? (string) $identity : null;
    }
}
