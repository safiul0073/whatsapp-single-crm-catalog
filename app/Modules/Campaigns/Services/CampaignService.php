<?php

namespace App\Modules\Campaigns\Services;

use App\Models\User;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\PrepareCampaignRecipientsJob;
use App\Modules\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected PlanLimitService $limits,
        protected AudienceResolver $audiences,
        protected CampaignRecipientService $recipients,
        protected CampaignReportService $reports,
        protected ChannelManager $channels,
        protected AutomationDispatcher $automations,
    ) {}

    public function listForUser(?User $user): LengthAwarePaginator
    {
        $workspace = $this->workspaces->current($user);

        return Campaign::query()
            ->where('workspace_id', $workspace->id)
            ->with('channelAccount')
            ->latest()
            ->paginate(20);
    }

    public function builderData(?User $user): array
    {
        $workspace = $this->workspaces->current($user);

        $campaignProviders = collect(config('marketing-channels.providers', []))
            ->where('campaign', true)
            ->keys()
            ->all();

        return [
            'providers' => $campaignProviders,
            'channels' => ChannelAccount::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('provider', $campaignProviders)
                ->where('status', ChannelAccountStatus::Connected->value)
                ->get(),
            'templates' => MessageTemplate::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('provider', ['whatsapp', 'telegram'])
                ->latest()
                ->get(),
            'automations' => Automation::query()
                ->where('workspace_id', $workspace->id)
                ->latest()
                ->get(),
            'contacts' => Contact::query()
                ->where('workspace_id', $workspace->id)
                ->latest()
                ->limit(200)
                ->get(),
            'tags' => ContactTag::query()->withCount('contacts')->where('workspace_id', $workspace->id)->latest()->get(),
            'groups' => ContactGroup::query()->where('workspace_id', $workspace->id)->latest()->get(),
            'canUseCampaignDoctor' => $this->limits->featureEnabled($workspace->id, CampaignDoctorService::FEATURE),
        ];
    }

    public function create(?User $user, array $data): Campaign
    {
        $workspace = $this->workspaces->current($user);
        $channel = $this->connectedChannel($workspace->id, (int) $data['channel_account_id']);
        $provider = $channel->provider;
        $messageType = $data['message_type'] ?? 'custom';

        $this->ensurePlanAllowsProvider($workspace->id, $provider);

        $this->validateCampaignContent($workspace->id, $provider, $messageType, $data);

        $audience = $this->audienceFromData($data);

        $contacts = $this->audiences->contacts($workspace->id, $audience);
        $sendableCount = $contacts->filter(fn (Contact $contact): bool => $this->audiences->sendability($contact, $provider) === null)->count();

        if (! $this->limits->allows($workspace->id, 'max_messages_per_month', $sendableCount)) {
            throw ValidationException::withMessages([
                'plan' => 'This campaign exceeds the current plan message quota.',
            ]);
        }

        if (! $this->limits->allows($workspace->id, 'max_contacts', $contacts->count())) {
            throw ValidationException::withMessages([
                'plan' => 'This campaign exceeds the current plan contact limit.',
            ]);
        }

        if (($data['use_ai_copy'] ?? false) && ! $this->limits->allows($workspace->id, 'max_ai_credits', 1)) {
            throw ValidationException::withMessages([
                'plan' => 'You do not have enough AI credits. Please upgrade your plan or buy more credits.',
            ]);
        }

        $scheduledAt = $this->scheduledAt($data);

        $status = CampaignStatus::Draft;
        if (($data['schedule'] ?? 'now') === 'now') {
            $status = $scheduledAt ? CampaignStatus::Scheduled : CampaignStatus::Sending;
        } elseif (($data['schedule'] ?? 'now') === 'later') {
            $status = CampaignStatus::Scheduled;
        }

        $attributes = [
            'workspace_id' => $workspace->id,
            'channel_account_id' => $channel->id,
            'provider' => $provider,
            'message_type' => $messageType,
            'message_template_id' => $messageType === 'template' ? ($data['message_template_id'] ?? null) : null,
            'automation_id' => $messageType === 'automation' ? ($data['automation_id'] ?? null) : null,
            'segment_id' => $data['segment_id'] ?? null,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'type' => $data['type'] ?? 'broadcast',
            'status' => $status->value,
            'audience_type' => $audience['audience_type'],
            'audience_ids' => $audience['audience_ids'],
            'message_subject' => $messageType === 'custom' ? ($data['message_subject'] ?? null) : null,
            'message_body' => $messageType === 'custom' ? ($data['message_body'] ?? null) : null,
            'variables' => $data['variables'] ?? [],
            'settings' => $data['settings'] ?? [],
            'scheduled_at' => $scheduledAt,
            'queued_at' => $status !== CampaignStatus::Draft ? now() : null,
            'started_at' => $status === CampaignStatus::Sending ? now() : null,
            'send_rate_per_minute' => (int) (($data['throttle'] ?? null) ?: 60),
        ];

        $this->channels->validateCampaign($channel, new Campaign($attributes));

        $campaign = Campaign::query()->create($attributes);

        if ($status !== CampaignStatus::Draft) {
            $this->limits->consume($workspace->id, 'max_campaigns_per_month', 1);
            PrepareCampaignRecipientsJob::dispatch($campaign->id)->delay($scheduledAt);
        }

        return $campaign->fresh('channelAccount');
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true)) {
            throw ValidationException::withMessages([
                'campaign' => 'Only draft or scheduled campaigns can be edited.',
            ]);
        }

        $channel = $this->connectedChannel($campaign->workspace_id, (int) $data['channel_account_id']);
        $provider = $channel->provider;
        $messageType = $data['message_type'] ?? 'custom';

        $this->validateCampaignContent($campaign->workspace_id, $provider, $messageType, $data);

        $audience = $this->audienceFromData($data);
        $scheduledAt = $this->scheduledAt($data);
        $status = match ($data['schedule'] ?? 'draft') {
            'now' => CampaignStatus::Sending,
            'later' => CampaignStatus::Scheduled,
            default => CampaignStatus::Draft,
        };

        $attributes = [
            'channel_account_id' => $channel->id,
            'provider' => $provider,
            'message_type' => $messageType,
            'message_template_id' => $messageType === 'template' ? ($data['message_template_id'] ?? null) : null,
            'automation_id' => $messageType === 'automation' ? ($data['automation_id'] ?? null) : null,
            'segment_id' => $data['segment_id'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'broadcast',
            'status' => $status->value,
            'audience_type' => $audience['audience_type'],
            'audience_ids' => $audience['audience_ids'],
            'message_subject' => $messageType === 'custom' ? ($data['message_subject'] ?? null) : null,
            'message_body' => $messageType === 'custom' ? ($data['message_body'] ?? null) : null,
            'variables' => $data['variables'] ?? [],
            'settings' => $data['settings'] ?? [],
            'scheduled_at' => $scheduledAt,
            'queued_at' => $status !== CampaignStatus::Draft ? now() : null,
            'started_at' => $status === CampaignStatus::Sending ? now() : null,
            'send_rate_per_minute' => (int) (($data['throttle'] ?? null) ?: 60),
        ];

        $candidate = $campaign->replicate();
        $candidate->forceFill(array_merge($attributes, ['workspace_id' => $campaign->workspace_id]));

        $this->channels->validateCampaign($channel, $candidate);

        $campaign->update($attributes);

        if ($status !== CampaignStatus::Draft) {
            $this->limits->consume($campaign->workspace_id, 'max_campaigns_per_month', 1);
            PrepareCampaignRecipientsJob::dispatch($campaign->id)->delay($scheduledAt);
        }

        return $campaign->fresh('channelAccount');
    }

    public function launch(Campaign $campaign): void
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled, CampaignStatus::Paused], true)) {
            throw ValidationException::withMessages([
                'campaign' => 'Campaign cannot be launched from its current status.',
            ]);
        }

        $campaign->update([
            'status' => CampaignStatus::Sending->value,
            'queued_at' => $campaign->queued_at ?? now(),
            'started_at' => now(),
        ]);

        $this->limits->consume($campaign->workspace_id, 'max_campaigns_per_month', 1);
        PrepareCampaignRecipientsJob::dispatch($campaign->id);
    }

    public function schedule(Campaign $campaign, Carbon $at): void
    {
        $campaign->update([
            'status' => CampaignStatus::Scheduled->value,
            'scheduled_at' => $at,
            'queued_at' => now(),
        ]);

        $this->limits->consume($campaign->workspace_id, 'max_campaigns_per_month', 1);
        PrepareCampaignRecipientsJob::dispatch($campaign->id)->delay($at);
    }

    public function pause(Campaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Sending) {
            return;
        }

        $campaign->update(['status' => CampaignStatus::Paused->value]);
    }

    public function resume(Campaign $campaign): void
    {
        if ($campaign->status !== CampaignStatus::Paused) {
            return;
        }

        $campaign->update(['status' => CampaignStatus::Sending->value]);

        $campaign->recipients()
            ->where('status', CampaignRecipientStatus::Queued)
            ->chunkById(500, function (Collection $recipients): void {
                foreach ($recipients as $recipient) {
                    SendCampaignRecipientJob::dispatch($recipient->id);
                }
            });
    }

    public function cancel(Campaign $campaign): void
    {
        if (in_array($campaign->status, [CampaignStatus::Completed, CampaignStatus::Cancelled, CampaignStatus::Failed], true)) {
            return;
        }

        $campaign->recipients()
            ->where('status', CampaignRecipientStatus::Queued)
            ->update([
                'status' => CampaignRecipientStatus::Failed->value,
                'error_message' => 'Campaign cancelled by user.',
                'failed_at' => now(),
            ]);

        $campaign->update(['status' => CampaignStatus::Cancelled->value]);
        $this->reports->refresh($campaign);
    }

    public function duplicate(Campaign $campaign): Campaign
    {
        $copy = $campaign->replicate([
            'uuid',
            'status',
            'total_recipients',
            'queued_count',
            'sending_count',
            'sent_count',
            'delivered_count',
            'opened_count',
            'read_count',
            'clicked_count',
            'replied_count',
            'failed_count',
            'skipped_count',
            'skipped_opt_out_count',
            'skipped_invalid_count',
            'skipped_policy_count',
            'scheduled_at',
            'queued_at',
            'started_at',
            'completed_at',
        ]);

        $copy->uuid = (string) Str::uuid();
        $copy->name = $this->copyName($copy->workspace_id, $copy->name);
        $copy->status = CampaignStatus::Draft->value;
        $copy->save();

        return $copy;
    }

    public function rerun(Campaign $campaign): Campaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Completed, CampaignStatus::Failed], true)) {
            throw ValidationException::withMessages([
                'campaign' => 'Only completed or failed campaigns can be re-run.',
            ]);
        }

        DB::transaction(function () use ($campaign): void {
            $campaign->recipients()->delete();

            $campaign->update([
                'status' => CampaignStatus::Sending->value,
                'total_recipients' => 0,
                'queued_count' => 0,
                'sending_count' => 0,
                'sent_count' => 0,
                'delivered_count' => 0,
                'opened_count' => 0,
                'read_count' => 0,
                'clicked_count' => 0,
                'replied_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'skipped_opt_out_count' => 0,
                'skipped_invalid_count' => 0,
                'skipped_policy_count' => 0,
                'scheduled_at' => null,
                'queued_at' => now(),
                'started_at' => now(),
                'completed_at' => null,
            ]);

            $this->limits->consume($campaign->workspace_id, 'max_campaigns_per_month', 1);
            PrepareCampaignRecipientsJob::dispatch($campaign->id)->afterCommit();
        });

        return $campaign->fresh('channelAccount');
    }

    public function prepareRecipients(Campaign $campaign): void
    {
        if (in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Paused, CampaignStatus::Cancelled, CampaignStatus::Completed, CampaignStatus::Failed], true)) {
            return;
        }

        $contacts = $this->audiences->contacts($campaign->workspace_id, [
            'audience_type' => $campaign->audience_type,
            'audience_ids' => $campaign->audience_ids,
            'segment_id' => $campaign->segment_id,
        ]);

        $campaign->update(['total_recipients' => $contacts->count()]);

        if ($campaign->message_type === 'automation') {
            $automation = Automation::query()
                ->where('workspace_id', $campaign->workspace_id)
                ->whereKey($campaign->automation_id)
                ->first();

            if (! $automation) {
                $this->failQueuedRecipients($campaign, 'Automation flow not found.');
                $this->reports->refresh($campaign);

                return;
            }

            foreach ($contacts->values() as $contact) {
                $recipient = $this->recipients->createForContact($campaign, $contact);

                if ($recipient->wasRecentlyCreated && $recipient->status === CampaignRecipientStatus::Queued) {
                    $this->automations->startAutomation($automation, [
                        'type' => 'campaign_started',
                        'workspace_id' => $campaign->workspace_id,
                        'provider' => $campaign->provider,
                        'channel_account_id' => $campaign->channel_account_id,
                        'campaign_id' => $campaign->id,
                        'campaign_recipient_id' => $recipient->id,
                        'contact_id' => $recipient->contact_id,
                        'recipient' => $recipient->recipient_address,
                        'event_key' => 'campaign-automation:'.$campaign->id.':'.$recipient->id,
                    ]);
                }
            }

            $this->reports->refresh($campaign);

            return;
        }

        foreach ($contacts->values() as $index => $contact) {
            $recipient = $this->recipients->createForContact($campaign, $contact);

            if ($recipient->wasRecentlyCreated && $recipient->status === CampaignRecipientStatus::Queued) {
                $dispatch = SendCampaignRecipientJob::dispatch($recipient->id);

                if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
                    $dispatch->delay($campaign->scheduled_at);
                } elseif ($campaign->send_rate_per_minute > 0) {
                    $dispatch->delay($this->dispatchDelay($campaign, $index));
                }
            }
        }

        $this->reports->refresh($campaign);
    }

    protected function dispatchDelay(Campaign $campaign, int $index): ?Carbon
    {
        $rate = $campaign->send_rate_per_minute;

        if ($rate <= 0) {
            return null;
        }

        $secondsBetween = 60 / $rate;

        return now()->addSeconds((int) floor($index * $secondsBetween));
    }

    protected function failQueuedRecipients(Campaign $campaign, string $message): void
    {
        $campaign->recipients()
            ->where('status', CampaignRecipientStatus::Queued)
            ->update([
                'status' => CampaignRecipientStatus::Failed->value,
                'error_message' => $message,
                'failed_at' => now(),
            ]);
    }

    protected function copyName(int $workspaceId, string $name): string
    {
        $base = $name.' Copy';
        $candidate = $base;
        $count = 2;

        while (Campaign::query()->where('workspace_id', $workspaceId)->where('name', $candidate)->exists()) {
            $candidate = $base.' '.$count;
            $count++;
        }

        return $candidate;
    }

    protected function ensurePlanAllowsProvider(int $workspaceId, string $provider): void
    {
        $subscription = Subscription::query()->with('plan')->where('workspace_id', $workspaceId)->latest()->first();
        $allowed = data_get($subscription?->plan?->limits, 'allowed_channels');

        if (is_array($allowed) && ! in_array($provider, $allowed, true)) {
            throw ValidationException::withMessages([
                'provider' => 'Your current plan does not allow '.ucfirst($provider).' campaigns.',
            ]);
        }
    }

    protected function connectedChannel(int $workspaceId, int $channelId): ChannelAccount
    {
        $campaignProviders = collect(config('marketing-channels.providers', []))
            ->where('campaign', true)
            ->keys()
            ->all();

        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('provider', $campaignProviders)
            ->where('status', ChannelAccountStatus::Connected->value)
            ->findOrFail($channelId);
    }

    protected function validateCampaignContent(int $workspaceId, string $provider, string $messageType, array $data): void
    {
        if ($messageType === 'template' && ! in_array($provider, ['whatsapp', 'telegram'], true)) {
            throw ValidationException::withMessages([
                'message_type' => 'Template campaigns are only supported for WhatsApp and Telegram senders.',
            ]);
        }

        if ($messageType === 'automation') {
            $automationId = $data['automation_id'] ?? null;

            if (blank($automationId) || ! Automation::query()->where('workspace_id', $workspaceId)->whereKey($automationId)->exists()) {
                throw ValidationException::withMessages([
                    'automation_id' => 'Select a saved automation flow from this workspace.',
                ]);
            }

            return;
        }

        if ($messageType === 'template') {
            if (blank($data['message_template_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'message_template_id' => 'Select an approved template.',
                ]);
            }

            $templateExists = MessageTemplate::query()
                ->where('workspace_id', $workspaceId)
                ->where('provider', $provider)
                ->whereKey($data['message_template_id'])
                ->exists();

            if (! $templateExists) {
                throw ValidationException::withMessages([
                    'message_template_id' => 'Select a template for the selected sender.',
                ]);
            }

            return;
        }

        if ($provider === 'email' && blank($data['message_subject'] ?? null)) {
            throw ValidationException::withMessages([
                'message_subject' => 'Email campaigns require a subject.',
            ]);
        }

        if (blank($data['message_body'] ?? null)) {
            throw ValidationException::withMessages([
                'message_body' => match ($provider) {
                    'email' => 'Email campaigns require a message body.',
                    'sms' => 'SMS campaigns require a message body.',
                    'telegram' => 'Telegram campaigns require a message body.',
                    'whatsapp' => 'WhatsApp custom campaigns require a message body.',
                    default => 'Campaigns require a message body.',
                },
            ]);
        }
    }

    protected function audienceFromData(array $data): array
    {
        return [
            'audience_type' => $data['audience_type'],
            'audience_ids' => array_values(array_filter((array) ($data['audience_ids'] ?? []))),
            'segment_id' => $data['segment_id'] ?? null,
        ];
    }

    protected function scheduledAt(array $data): ?Carbon
    {
        if (($data['schedule'] ?? 'now') !== 'later') {
            return null;
        }

        if (blank($data['send_date'] ?? null) || blank($data['send_time'] ?? null)) {
            throw ValidationException::withMessages([
                'send_date' => 'Scheduled campaigns require a send date and time.',
            ]);
        }

        return Carbon::parse($data['send_date'].' '.$data['send_time'], $data['timezone'] ?? config('app.timezone'))->utc();
    }
}
