<?php

namespace App\Modules\WhatsAppCloud\Services;

use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;

/**
 * WhatsApp delivers edits, deletions and reactions as separate inbound messages that point at an
 * earlier one. They change that original message instead of becoming bubbles of their own.
 */
class InboundMessageEventApplier
{
    public const EVENT_TYPES = ['reaction', 'edit', 'revoke'];

    /**
     * @param  array<string, mixed>  $payload
     * @return bool true when the event was applied (or deliberately dropped) and needs no message row
     */
    public function apply(ChannelAccount $account, array $payload): bool
    {
        $type = (string) ($payload['type'] ?? '');

        if (! in_array($type, self::EVENT_TYPES, true)) {
            return false;
        }

        $original = $this->originalMessage($account, $type, $payload);

        if (! $original) {
            return $type !== 'edit';
        }

        match ($type) {
            'edit' => $this->applyEdit($original, $payload),
            'revoke' => $this->applyRevoke($original),
            'reaction' => $this->applyReaction($original, $payload),
        };

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function originalMessage(ChannelAccount $account, string $type, array $payload): ?Message
    {
        $originalId = match ($type) {
            'edit' => data_get($payload, 'edit.original_message_id'),
            'revoke' => data_get($payload, 'revoke.original_message_id'),
            default => data_get($payload, 'reaction.message_id'),
        };

        if (blank($originalId)) {
            return null;
        }

        return Message::query()
            ->where('channel_account_id', $account->id)
            ->where('provider_message_id', $originalId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function applyEdit(Message $original, array $payload): void
    {
        $edited = data_get($payload, 'edit.message');
        $newBody = data_get($edited, 'text.body') ?: data_get($edited, 'image.caption') ?: data_get($edited, 'video.caption') ?: data_get($edited, 'document.caption');

        if (blank($newBody)) {
            return;
        }

        $messagePayload = (array) $original->payload;
        $messagePayload['original_body'] ??= $original->body;
        $messagePayload['edited_at'] = now()->toIso8601String();
        $original->update(['body' => (string) $newBody, 'payload' => $messagePayload]);
    }

    protected function applyRevoke(Message $original): void
    {
        $messagePayload = (array) $original->payload;
        $messagePayload['deleted_at'] ??= now()->toIso8601String();
        $original->update(['payload' => $messagePayload]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function applyReaction(Message $original, array $payload): void
    {
        $messagePayload = (array) $original->payload;
        $reactions = (array) ($messagePayload['reactions'] ?? []);
        $reactor = (string) ($payload['from'] ?? 'unknown');
        $emoji = (string) data_get($payload, 'reaction.emoji', '');

        if ($emoji === '') {
            unset($reactions[$reactor]);
        } else {
            $reactions[$reactor] = $emoji;
        }

        $messagePayload['reactions'] = $reactions;
        $original->update(['payload' => $messagePayload]);
    }
}
