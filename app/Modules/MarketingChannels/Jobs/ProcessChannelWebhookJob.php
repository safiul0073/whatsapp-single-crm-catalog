<?php

namespace App\Modules\MarketingChannels\Jobs;

use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\AutoReplies\Services\AutoReplyService;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Jobs\UpdateCampaignStatsJob;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Commerce\Services\OrderIntakeService;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Crm\Services\CRMLeadService;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use App\Modules\Telegram\Services\TelegramOptInService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ProcessChannelWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $eventId) {}

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(ChannelManager $channels, AutomationDispatcher $automations, AutoReplyService $autoReplies, ?SubscriptionAccessService $subscriptions = null, ?CRMLeadService $crmLeads = null): void
    {
        $subscriptions ??= app(SubscriptionAccessService::class);
        $crmLeads ??= app(CRMLeadService::class);

        $event = ChannelWebhookEvent::query()->find($this->eventId);

        if (! $event || $event->processed_at) {
            return;
        }

        try {
            $account = $event->channelAccount;

            if (! $account) {
                throw new \RuntimeException('Channel account not found for webhook event.');
            }

            if (! $subscriptions->canUseServices((int) $account->workspace_id)) {
                $event->update([
                    'status' => ChannelWebhookEventStatus::Processed->value,
                    'processed_at' => now(),
                    'error' => 'subscription_expired',
                ]);

                return;
            }

            $result = $channels->processWebhook($account, $event->payload ?? []);

            foreach ($result['events'] ?? [] as $normalizedEvent) {
                foreach ($this->applyEvent($account, $normalizedEvent, $autoReplies, $crmLeads) as $automationEvent) {
                    $automations->dispatch($automationEvent);
                }
            }

            $event->update([
                'status' => ChannelWebhookEventStatus::Processed->value,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $finalAttempt = $this->attempts() >= $this->tries;
            $event->update([
                'status' => $finalAttempt ? ChannelWebhookEventStatus::Failed->value : ChannelWebhookEventStatus::Pending->value,
                'failed_at' => $finalAttempt ? now() : null,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function applyEvent(ChannelAccount $account, array $event, AutoReplyService $autoReplies, CRMLeadService $crmLeads): array
    {
        $type = $event['type'] ?? 'unknown';

        if ($type === 'status') {
            return $this->applyStatusEvent($account, $event);
        }

        if ($type === 'message') {
            return $this->applyMessageEvent($account, $event, $autoReplies, $crmLeads);
        }

        return [];
    }

    protected function applyStatusEvent(ChannelAccount $account, array $event): array
    {
        $messageId = $event['provider_message_id'] ?? null;
        $status = $event['status'] ?? null;

        if (blank($messageId) || blank($status)) {
            return [];
        }

        $recipientStatus = $this->mapRecipientStatus($status);

        $recipient = CampaignRecipient::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('provider', $account->provider)
            ->where('provider_message_id', $messageId)
            ->first();

        if ($recipient && $recipientStatus) {
            $updates = [
                'status' => $recipientStatus->value,
            ];

            $timestampColumn = match ($recipientStatus) {
                CampaignRecipientStatus::Delivered => 'delivered_at',
                CampaignRecipientStatus::Opened => 'opened_at',
                CampaignRecipientStatus::Read => 'read_at',
                CampaignRecipientStatus::Clicked => 'clicked_at',
                CampaignRecipientStatus::Replied => 'replied_at',
                CampaignRecipientStatus::Failed => 'failed_at',
                default => null,
            };

            if ($timestampColumn) {
                $updates[$timestampColumn] = now();
            }

            $recipient->update($updates);

            if ($recipient->campaign_id) {
                UpdateCampaignStatsJob::dispatch($recipient->campaign_id);
            }
        }

        Message::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('provider', $account->provider)
            ->where('provider_message_id', $messageId)
            ->update(['status' => $this->mapMessageStatus($status)]);

        return [[
            'type' => 'message_status',
            'workspace_id' => $account->workspace_id,
            'provider' => $account->provider,
            'channel_account_id' => $account->id,
            'message_id' => Message::query()
                ->where('workspace_id', $account->workspace_id)
                ->where('provider', $account->provider)
                ->where('provider_message_id', $messageId)
                ->value('id'),
            'campaign_id' => $recipient?->campaign_id,
            'campaign_recipient_id' => $recipient?->id,
            'contact_id' => $recipient?->contact_id,
            'status' => strtolower((string) $status),
            'event_key' => 'status:'.$account->id.':'.$messageId.':'.strtolower((string) $status),
            'payload' => $event['payload'] ?? [],
        ]];
    }

    protected function applyMessageEvent(ChannelAccount $account, array $event, AutoReplyService $autoReplies, CRMLeadService $crmLeads): array
    {
        $providerContactId = $event['provider_contact_id'] ?? null;
        $body = $event['body'] ?? null;

        if (blank($providerContactId)) {
            return [];
        }

        $identity = $this->telegramOptInIdentity($account, $event);

        $identity ??= ContactProviderIdentity::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('provider', $account->provider)
            ->where('provider_contact_id', $providerContactId)
            ->first();

        $identity ??= $this->createIdentityForMessage($account, $event, (string) $providerContactId);

        $identity->update(['last_interaction_at' => now()]);

        $conversation = Conversation::query()->firstOrCreate(
            [
                'workspace_id' => $account->workspace_id,
                'provider' => $account->provider,
                'contact_id' => $identity->contact_id,
                'channel_account_id' => $account->id,
            ],
            [
                'provider_conversation_id' => $providerContactId,
                'status' => ConversationStatus::Open->value,
                'labels' => [],
            ]
        );

        $payload = $event['payload'] ?? [];
        $message = Message::query()->updateOrCreate(
            ['provider_message_id' => $event['provider_message_id'] ?? (string) Str::uuid()],
            [
                'workspace_id' => $account->workspace_id,
                'conversation_id' => $conversation->id,
                'contact_id' => $identity->contact_id,
                'channel_account_id' => $account->id,
                'provider' => $account->provider,
                'direction' => 'inbound',
                'type' => $payload['type'] ?? 'text',
                'body' => $body,
                'payload' => $payload,
                'status' => MessageStatus::Received->value,
            ]
        );

        $conversation->update([
            'last_message_at' => now(),
            'session_expires_at' => $account->provider === 'whatsapp' ? now()->addHours(24) : $conversation->session_expires_at,
        ]);

        if ($account->provider === 'whatsapp' && ($payload['type'] ?? null) === 'order') {
            app(OrderIntakeService::class)->intake($account, $identity->contact, $conversation, $payload);
        }

        $autoReplies->replyToInbound($account, $conversation->fresh(['contact', 'channelAccount.workspace']), $message);

        $automationEvents = [[
            'type' => 'message_received',
            'workspace_id' => $account->workspace_id,
            'provider' => $account->provider,
            'channel_account_id' => $account->id,
            'contact_id' => $identity->contact_id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'body' => $body,
            'event_key' => 'message:'.$account->id.':'.$message->provider_message_id,
            'payload' => $event['payload'] ?? [],
        ]];

        if ($button = $this->buttonPayload($event)) {
            $automationEvents[] = array_merge($automationEvents[0], [
                'type' => 'button_clicked',
                'button_id' => $button['id'] ?? null,
                'button_text' => $button['text'] ?? null,
                'event_key' => 'button:'.$account->id.':'.$message->provider_message_id.':'.($button['id'] ?? $button['text'] ?? 'unknown'),
            ]);
        }

        if ($campaignRecipient = $this->latestCampaignRecipientForReply($account, $identity->contact_id)) {
            $campaignRecipient->update([
                'status' => CampaignRecipientStatus::Replied->value,
                'replied_at' => now(),
            ]);
            UpdateCampaignStatsJob::dispatch($campaignRecipient->campaign_id);
            $campaignRecipient->loadMissing('campaign');
            if ($account->provider === 'whatsapp' && $campaignRecipient->campaign) {
                $crmLeads->handleCampaignReply(
                    (int) $account->workspace_id,
                    (int) $identity->contact_id,
                    (int) $conversation->id,
                    $campaignRecipient->campaign,
                );
            }

            $automationEvents[] = array_merge($automationEvents[0], [
                'type' => 'campaign_replied',
                'campaign_id' => $campaignRecipient->campaign_id,
                'campaign_recipient_id' => $campaignRecipient->id,
                'event_key' => 'campaign-reply:'.$campaignRecipient->id.':'.$message->id,
            ]);
        }

        return $automationEvents;
    }

    protected function latestCampaignRecipientForReply(ChannelAccount $account, int $contactId): ?CampaignRecipient
    {
        return CampaignRecipient::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('provider', $account->provider)
            ->where('contact_id', $contactId)
            ->whereIn('status', [
                CampaignRecipientStatus::Sent->value,
                CampaignRecipientStatus::Delivered->value,
                CampaignRecipientStatus::Read->value,
            ])
            ->latest('sent_at')
            ->latest('id')
            ->first();
    }

    protected function buttonPayload(array $event): ?array
    {
        $payload = $event['payload'] ?? [];

        return data_get($payload, 'button')
            ?: data_get($payload, 'interactive.button_reply')
            ?: data_get($payload, 'interactive.list_reply');
    }

    protected function createIdentityForMessage(ChannelAccount $account, array $event, string $providerContactId): ContactProviderIdentity
    {
        $username = $event['username'] ?? null;
        $name = filled($username)
            ? '@'.ltrim((string) $username, '@')
            : $this->contactNameFor($account, $providerContactId);

        $contactData = [
            'workspace_id' => $account->workspace_id,
            'name' => $name,
            'last_interaction_at' => now(),
        ];

        if ($account->provider === 'telegram') {
            $contactData['opt_in_status'] = ContactOptInStatus::Subscribed->value;
            $contactData['opt_in_at'] = now();
        }

        $contact = Contact::query()->create($contactData);

        return ContactProviderIdentity::query()->create([
            'workspace_id' => $account->workspace_id,
            'contact_id' => $contact->id,
            'channel_account_id' => $account->id,
            'provider' => $account->provider,
            'provider_contact_id' => $providerContactId,
            'username' => $username,
            'identity_type' => $account->provider === 'telegram' ? 'telegram_user_id' : 'provider_user_id',
            'status' => 'active',
            'metadata' => $event['payload'] ?? [],
            'last_interaction_at' => now(),
        ]);
    }

    protected function telegramOptInIdentity(ChannelAccount $account, array $event): ?ContactProviderIdentity
    {
        if ($account->provider !== 'telegram') {
            return null;
        }

        $optIns = app(TelegramOptInService::class);

        if (filled($event['start_token'] ?? null)) {
            $identity = $optIns->linkFromToken($account, (string) $event['start_token'], $event);

            if ($identity) {
                return $identity;
            }
        }

        return $optIns->linkFromSharedPhone($account, $event);
    }

    protected function contactNameFor(ChannelAccount $account, string $providerContactId): string
    {
        return Str::of((string) $account->provider)
            ->replace(['_', '-'], ' ')
            ->title()
            ->append(' ', $providerContactId)
            ->toString();
    }

    protected function mapRecipientStatus(string $status): ?CampaignRecipientStatus
    {
        $value = strtolower($status);

        return match ($value) {
            'sent' => CampaignRecipientStatus::Sent,
            'delivered' => CampaignRecipientStatus::Delivered,
            'opened' => CampaignRecipientStatus::Opened,
            'read' => CampaignRecipientStatus::Read,
            'clicked' => CampaignRecipientStatus::Clicked,
            'replied' => CampaignRecipientStatus::Replied,
            'failed' => CampaignRecipientStatus::Failed,
            default => null,
        };
    }

    protected function mapMessageStatus(string $status): string
    {
        return MessageStatus::tryFrom(strtolower($status))?->value ?? MessageStatus::Sent->value;
    }
}
