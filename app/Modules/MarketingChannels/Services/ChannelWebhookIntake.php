<?php

namespace App\Modules\MarketingChannels\Services;

use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Jobs\ProcessChannelWebhookJob;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;

/**
 * Stores an incoming channel webhook once and hands it to ProcessChannelWebhookJob, which runs the
 * inbox, push alerts, auto-replies, the AI bot, automations and campaign tracking.
 */
class ChannelWebhookIntake
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function accept(ChannelAccount $account, string $provider, array $payload, array $headers = []): ChannelWebhookEvent
    {
        $event = ChannelWebhookEvent::query()->firstOrCreate(
            ['payload_hash' => sha1(json_encode($payload).$account->id)],
            [
                'channel_account_id' => $account->id,
                'workspace_id' => $account->workspace_id,
                'provider' => $provider,
                'event_type' => $this->eventType($payload),
                'payload' => $payload,
                'headers' => $headers,
                'status' => ChannelWebhookEventStatus::Pending->value,
            ]
        );

        if (! $event->processed_at && ! $event->failed_at) {
            if ($provider === 'telegram') {
                ProcessChannelWebhookJob::dispatchSync($event->id);
            } else {
                ProcessChannelWebhookJob::dispatchAfterResponse($event->id);
            }
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function eventType(array $payload): string
    {
        $messaging = collect((array) data_get($payload, 'entry', []))->flatMap(fn ($entry): array => (array) data_get($entry, 'messaging', []));

        if (filled(data_get($payload, 'entry.0.changes.0.value.messages')) || filled(data_get($payload, 'message')) || $messaging->contains(fn ($item): bool => filled(data_get($item, 'message')))) {
            return 'message.received';
        }

        if ($messaging->contains(fn ($item): bool => filled(data_get($item, 'optin')))) {
            return 'optin';
        }

        if (data_get($payload, 'entry.0.changes.0.field') === 'comments') {
            return 'comment.received';
        }

        if (filled(data_get($payload, 'entry.0.changes.0.value.statuses')) || filled(data_get($payload, 'delivery.mids')) || $messaging->contains(fn ($item): bool => filled(data_get($item, 'delivery')) || filled(data_get($item, 'read')))) {
            return 'message.status';
        }

        return 'unknown';
    }
}
