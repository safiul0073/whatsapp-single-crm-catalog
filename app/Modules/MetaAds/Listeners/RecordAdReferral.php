<?php

namespace App\Modules\MetaAds\Listeners;

use App\Modules\Inbox\Models\Message;
use App\Modules\MetaAds\Models\MetaAdReferral;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Meta attaches a "referral" to the first message sent from a click-to-message ad. Recording it
 * ties the chat (and any order that follows) back to the ad that started it.
 */
class RecordAdReferral
{
    public function handle(Message $message): void
    {
        if ($message->direction !== 'inbound' || ! in_array($message->provider, ['whatsapp', 'messenger', 'instagram'], true)) {
            return;
        }

        $referral = $this->referral($message->payload ?? []);

        if ($referral === null) {
            return;
        }

        try {
            MetaAdReferral::query()->firstOrCreate(
                ['message_id' => $message->id],
                array_merge($referral, [
                    'workspace_id' => $message->workspace_id,
                    'conversation_id' => $message->conversation_id,
                    'contact_id' => $message->contact_id,
                    'channel_account_id' => $message->channel_account_id,
                    'provider' => $message->provider,
                    'referred_at' => $message->created_at ?? now(),
                ])
            );
        } catch (\Throwable $exception) {
            Log::warning('Could not record Meta ad referral.', ['message_id' => $message->id, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * WhatsApp sends message.referral; Messenger and Instagram send referral on the messaging event
     * or inside message.referral, with source "ADS" for ads.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>|null
     */
    protected function referral(array $payload): ?array
    {
        $referral = $payload['referral'] ?? data_get($payload, 'message.referral') ?? data_get($payload, 'postback.referral');

        if (! is_array($referral)) {
            return null;
        }

        $sourceType = strtolower((string) ($referral['source_type'] ?? $referral['source'] ?? ''));
        $adId = $referral['source_id'] ?? $referral['ad_id'] ?? null;

        $isAd = in_array($sourceType, ['ad', 'ads'], true) || ($sourceType === '' && filled($adId));

        if (! $isAd) {
            return null;
        }

        return [
            'ad_id' => filled($adId) ? (string) $adId : null,
            'ctwa_clid' => filled($referral['ctwa_clid'] ?? null) ? (string) $referral['ctwa_clid'] : null,
            'source_type' => $sourceType !== '' ? $sourceType : 'ad',
            'source_url' => filled($referral['source_url'] ?? null) ? Str::limit((string) $referral['source_url'], 2040, '') : null,
            'headline' => filled($referral['headline'] ?? data_get($referral, 'ads_context_data.ad_title')) ? Str::limit((string) ($referral['headline'] ?? data_get($referral, 'ads_context_data.ad_title')), 250, '') : null,
            'body' => filled($referral['body'] ?? null) ? (string) $referral['body'] : null,
            'media_type' => filled($referral['media_type'] ?? null) ? (string) $referral['media_type'] : null,
        ];
    }
}
