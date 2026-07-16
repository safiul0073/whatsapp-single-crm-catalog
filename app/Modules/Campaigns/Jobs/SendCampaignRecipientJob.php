<?php

namespace App\Modules\Campaigns\Jobs;

use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignRecipientService;
use App\Modules\Campaigns\Services\CampaignReportService;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaignRecipientJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $recipientId) {}

    public function handle(ChannelManager $channels, CampaignRecipientService $service, CampaignReportService $reports, ?SubscriptionAccessService $subscriptions = null): void
    {
        $subscriptions ??= app(SubscriptionAccessService::class);

        $recipient = CampaignRecipient::query()->with(['campaign', 'contact'])->find($this->recipientId);

        if (! $recipient || $recipient->isTerminal()) {
            return;
        }

        $campaign = $recipient->campaign;

        if (! $campaign) {
            $service->markFailed($recipient, 'missing_campaign', 'Campaign not found.');

            return;
        }

        if (in_array($campaign->status, [CampaignStatus::Paused, CampaignStatus::Cancelled], true)) {
            return;
        }

        if (! $subscriptions->canUseServices((int) $campaign->workspace_id)) {
            $service->markFailed($recipient, 'subscription_expired', __(SubscriptionAccessService::EXPIRED_MESSAGE));
            $reports->refresh($campaign);

            return;
        }

        $service->transition($recipient, CampaignRecipientStatus::Sending);

        $account = ChannelAccount::query()->find($recipient->channel_account_id);

        if (! $account) {
            $service->markFailed($recipient, 'missing_channel', 'Channel account not found.');
            $reports->refresh($campaign);

            return;
        }

        $payload = $service->buildPayload($campaign, $recipient);
        $result = $channels->sendMessage($account, ['to' => $recipient->recipient_address], $payload);

        if ($result['ok'] ?? false) {
            $service->transition($recipient, CampaignRecipientStatus::tryFrom($result['status'] ?? 'sent') ?? CampaignRecipientStatus::Sent);
            $recipient->update(['provider_message_id' => $result['provider_message_id'] ?? null]);
        } else {
            $service->markFailed(
                $recipient,
                $result['error_code'] ?? 'provider_error',
                $result['error'] ?? (isset($result['response']) ? json_encode($result['response']) : 'Provider send failed.')
            );
        }

        $recipient->refresh();
        $this->persistOutboundMessage($recipient, $account, $payload);

        $reports->refresh($campaign);
    }

    protected function persistOutboundMessage(CampaignRecipient $recipient, ChannelAccount $account, array $payload): void
    {
        $conversation = $this->conversationFor($recipient, $account);

        Message::query()->create([
            'workspace_id' => $recipient->workspace_id,
            'channel_account_id' => $account->id,
            'provider' => $account->provider,
            'conversation_id' => $conversation?->id,
            'contact_id' => $recipient->contact_id,
            'direction' => 'outbound',
            'type' => $payload['type'] ?? 'text',
            'body' => $payload['body'] ?? ($payload['html_body'] ?? null),
            'payload' => $payload,
            'status' => $this->messageStatus($recipient),
            'provider_message_id' => $recipient->provider_message_id,
            'campaign_id' => $recipient->campaign_id,
            'whatsapp_message_id' => $account->provider === 'whatsapp' ? $recipient->provider_message_id : null,
        ]);

        $conversation?->update([
            'last_message_at' => now(),
            'status' => ConversationStatus::Open->value,
        ]);
    }

    protected function conversationFor(CampaignRecipient $recipient, ChannelAccount $account): ?Conversation
    {
        if (! $recipient->contact_id) {
            return null;
        }

        return Conversation::query()->firstOrCreate(
            [
                'workspace_id' => $recipient->workspace_id,
                'channel_account_id' => $account->id,
                'provider' => $account->provider,
                'contact_id' => $recipient->contact_id,
            ],
            [
                'provider_conversation_id' => $recipient->recipient_address,
                'status' => ConversationStatus::Open->value,
                'labels' => [],
            ]
        );
    }

    protected function messageStatus(CampaignRecipient $recipient): string
    {
        $status = $recipient->status instanceof CampaignRecipientStatus ? $recipient->status->value : (string) $recipient->status;

        return MessageStatus::tryFrom($status)?->value ?? MessageStatus::Sent->value;
    }
}
