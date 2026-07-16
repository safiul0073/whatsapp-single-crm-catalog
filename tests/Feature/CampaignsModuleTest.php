<?php

use App\Models\User;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\AutoReplies\Services\AutoReplyService;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Jobs\PrepareCampaignRecipientsJob;
use App\Modules\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Campaigns\Services\CampaignRecipientService;
use App\Modules\Campaigns\Services\CampaignReportService;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Jobs\ProcessChannelWebhookJob;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MessageTemplates\Models\MessageTemplateSubmission;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function campaignUserContext(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $workspace->update(['settings' => array_merge($workspace->settings ?? [], [
        'onboarding_completed_at' => now()->toIso8601String(),
    ])]);
    $plan = Plan::query()->firstOrCreate(
        ['slug' => 'test-plan'],
        ['name' => 'Test Plan', 'price' => 0, 'interval' => 'month', 'limits' => [], 'features' => [], 'is_active' => true]
    );
    Subscription::query()->updateOrCreate(
        ['workspace_id' => $workspace->id],
        ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'renews_at' => now()->addMonth(), 'usage' => []]
    );
    foreach (['campaigns.view', 'campaigns.create', 'campaigns.manage', 'templates.manage'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo(['campaigns.view', 'campaigns.create', 'campaigns.manage', 'templates.manage']);

    return [$user, $workspace];
}

function campaignChannel(int $workspaceId, string $provider): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspaceId,
        'provider' => $provider,
        'name' => ucfirst($provider).' Channel',
        'status' => 'connected',
        'credentials' => match ($provider) {
            'telegram' => ['access_token' => 'telegram-token'],
            'sms' => ['sms_provider' => 'log'],
            'email' => ['mail_mailer' => 'log', 'mail_from_name' => 'WaPro'],
            default => ['access_token' => 'whatsapp-token'],
        },
        'webhook_verify_token' => 'verify-'.Str::random(8),
        'provider_account_id' => $provider === 'whatsapp' ? 'waba-123' : $provider.'-account',
        'provider_phone_id' => $provider === 'whatsapp' ? 'phone-123' : null,
        'provider_display_id' => $provider === 'email' ? 'noreply@example.com' : '+15555550123',
        'settings' => [],
        'connected_at' => now(),
    ]);
}

function campaignGroupWithContacts(int $workspaceId, array $contacts = []): ContactGroup
{
    $group = ContactGroup::query()->create([
        'workspace_id' => $workspaceId,
        'name' => 'Launch Group',
        'slug' => 'launch-group',
        'type' => 'static',
    ]);

    foreach ($contacts as $contact) {
        $group->contacts()->attach($contact->id);
    }

    return $group;
}

function campaignContact(int $workspaceId, array $overrides = []): Contact
{
    static $phoneOffset = 100;
    static $emailOffset = 100;
    $phoneOffset++;
    $emailOffset++;

    return Contact::query()->create(array_merge([
        'workspace_id' => $workspaceId,
        'name' => 'Ada Lovelace',
        'phone' => '+1555555'.str_pad((string) $phoneOffset, 4, '0', STR_PAD_LEFT),
        'email' => 'ada'.$emailOffset.'@example.com',
        'opt_in_status' => ContactOptInStatus::Subscribed->value,
        'opt_in_at' => now(),
    ], $overrides));
}

function campaignDoctorPlan(int $workspaceId, bool $enabled = true, ?int $credits = null, int $used = 0): Subscription
{
    $limits = [
        'campaign_ai_doctor' => $enabled,
    ];

    if ($credits !== null) {
        $limits['max_ai_credits'] = $credits;
    }

    $plan = Plan::query()->create([
        'name' => $enabled ? 'Doctor Premium' : 'Doctor Free',
        'slug' => ($enabled ? 'doctor-premium-' : 'doctor-free-').$workspaceId.'-'.($credits ?? 'unlimited').'-'.$used,
        'price' => $enabled ? 29 : 0,
        'interval' => 'month',
        'limits' => $limits,
        'features' => $enabled ? ['AI Campaign Doctor'] : ['Basic campaigns'],
        'is_active' => true,
    ]);

    return Subscription::query()->updateOrCreate(
        ['workspace_id' => $workspaceId],
        ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'renews_at' => now()->addMonth(), 'usage' => ['max_ai_credits' => $used]]
    );
}

function campaignDoctorPayload(ChannelAccount $channel, ContactGroup $group, array $overrides = []): array
{
    return array_merge([
        'name' => 'Doctor check campaign',
        'channel_account_id' => $channel->id,
        'message_type' => 'custom',
        'audience_type' => 'groups',
        'audience_ids' => [$group->id],
        'message_body' => 'Hello from WaPro',
        'schedule' => 'draft',
        'timezone' => 'Asia/Dhaka',
    ], $overrides);
}

it('returns an ai campaign doctor report for premium plans', function (): void {
    [$user, $workspace] = campaignUserContext();
    $subscription = campaignDoctorPlan($workspace->id, true, 5);
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id, ['country' => 'US']);
    $group = campaignGroupWithContacts($workspace->id, [$contact]);

    $this->actingAs($user)
        ->postJson(route('user.campaigns.doctor'), campaignDoctorPayload($channel, $group))
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonStructure([
            'enabled',
            'score',
            'summary',
            'items' => [
                '*' => ['key', 'label', 'severity', 'message', 'meta'],
            ],
            'cost',
            'best_send_time',
        ])
        ->assertJsonPath('best_send_time.label', '9-11 AM local time');

    expect((int) data_get($subscription->fresh()->usage, 'max_ai_credits'))->toBe(1);
});

it('rejects ai campaign doctor reports when platform ai credits are over', function (): void {
    [$user, $workspace] = campaignUserContext();
    $subscription = campaignDoctorPlan($workspace->id, true, 1, 1);
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id);
    $group = campaignGroupWithContacts($workspace->id, [$contact]);

    $this->actingAs($user)
        ->postJson(route('user.campaigns.doctor'), campaignDoctorPayload($channel, $group))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan')
        ->assertJsonPath('errors.plan.0', 'Your platform AI credits are over. Please upgrade your plan or buy more credits.');

    expect((int) data_get($subscription->fresh()->usage, 'max_ai_credits'))->toBe(1);
});

it('blocks ai campaign doctor reports for non premium plans', function (): void {
    [$user, $workspace] = campaignUserContext();
    campaignDoctorPlan($workspace->id, false);
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id);
    $group = campaignGroupWithContacts($workspace->id, [$contact]);

    $this->actingAs($user)
        ->postJson(route('user.campaigns.doctor'), campaignDoctorPayload($channel, $group))
        ->assertForbidden()
        ->assertJsonPath('enabled', false)
        ->assertJsonPath('summary', 'AI Campaign Doctor is available on premium plans. Upgrade to review campaign risk before sending.');
});

it('counts opt in and blocked audience risks in the doctor report', function (): void {
    [$user, $workspace] = campaignUserContext();
    campaignDoctorPlan($workspace->id);
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $sendable = campaignContact($workspace->id);
    $missingOptIn = campaignContact($workspace->id, [
        'opt_in_status' => ContactOptInStatus::Unknown->value,
        'opt_in_at' => null,
    ]);
    $blocked = campaignContact($workspace->id, ['blocked_at' => now()]);
    $group = campaignGroupWithContacts($workspace->id, [$sendable, $missingOptIn, $blocked]);

    $response = $this->actingAs($user)
        ->postJson(route('user.campaigns.doctor'), campaignDoctorPayload($channel, $group))
        ->assertOk();

    $optInRisk = collect($response->json('items'))->firstWhere('key', 'opt_in_risk');

    expect($optInRisk['severity'])->toBe('high')
        ->and($optInRisk['meta']['blocked'])->toBe(1)
        ->and($optInRisk['meta']['missingOptIn'])->toBe(1)
        ->and($optInRisk['message'])->toContain('contacts have no confirmed opt-in source');
});

it('warns when a utility template sounds promotional', function (): void {
    [$user, $workspace] = campaignUserContext();
    campaignDoctorPlan($workspace->id);
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id);
    $group = campaignGroupWithContacts($workspace->id, [$contact]);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'order_update_offer',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [['type' => 'BODY', 'text' => 'Your order is ready. Limited time discount available today.']],
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('user.campaigns.doctor'), campaignDoctorPayload($channel, $group, [
            'message_type' => 'template',
            'message_template_id' => $template->id,
        ]))
        ->assertOk();

    $templateRisk = collect($response->json('items'))->firstWhere('key', 'template_risk');

    expect($templateRisk['severity'])->toBe('medium')
        ->and($templateRisk['message'])->toBe('This utility template sounds promotional.');
});

it('warns when audience fatigue is detected', function (): void {
    [$user, $workspace] = campaignUserContext();
    campaignDoctorPlan($workspace->id);
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id);
    $group = campaignGroupWithContacts($workspace->id, [$contact]);

    foreach (range(1, 3) as $index) {
        $campaign = Campaign::query()->create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $channel->id,
            'provider' => 'whatsapp',
            'uuid' => (string) Str::uuid(),
            'name' => 'Prior campaign '.$index,
            'status' => CampaignStatus::Completed->value,
            'audience_type' => 'groups',
            'send_rate_per_minute' => 60,
        ]);

        CampaignRecipient::query()->create([
            'workspace_id' => $workspace->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $channel->id,
            'provider' => 'whatsapp',
            'uuid' => (string) Str::uuid(),
            'recipient_address' => $contact->phone,
            'status' => CampaignRecipientStatus::Sent->value,
            'sent_at' => now()->subDays(2),
        ]);
    }

    $response = $this->actingAs($user)
        ->postJson(route('user.campaigns.doctor'), campaignDoctorPayload($channel, $group))
        ->assertOk();

    $fatigue = collect($response->json('items'))->firstWhere('key', 'audience_fatigue');

    expect($fatigue['severity'])->toBe('medium')
        ->and($fatigue['meta']['fatigued_contacts'])->toBe(1);
});

it('forecasts meta cost from configured rates and sendable contacts', function (): void {
    config(['campaign-doctor.whatsapp_rates.countries.US.marketing' => 0.5]);

    [$user, $workspace] = campaignUserContext();
    campaignDoctorPlan($workspace->id);
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $first = campaignContact($workspace->id, ['country' => 'US']);
    $second = campaignContact($workspace->id, ['country' => 'US']);
    $blocked = campaignContact($workspace->id, ['country' => 'US', 'blocked_at' => now()]);
    $group = campaignGroupWithContacts($workspace->id, [$first, $second, $blocked]);

    $this->actingAs($user)
        ->postJson(route('user.campaigns.doctor'), campaignDoctorPayload($channel, $group, [
            'message_body' => 'Normal update',
        ]))
        ->assertOk()
        ->assertJsonPath('cost.available', true)
        ->assertJsonPath('cost.sendable_count', 2)
        ->assertJsonPath('cost.total', 1)
        ->assertJsonPath('cost.formatted_total', '$1.00');
});

it('validates required message fields per non-whatsapp provider', function (): void {
    [$user, $workspace] = campaignUserContext();

    foreach (['email', 'sms', 'telegram'] as $provider) {
        $channel = campaignChannel($workspace->id, $provider);

        $this->actingAs($user)
            ->from(route('user.campaigns.create'))
            ->post(route('user.campaigns.store'), [
                'name' => ucfirst($provider).' Campaign',
                'provider' => $provider,
                'channel_account_id' => $channel->id,
                'message_type' => 'custom',
                'audience_type' => 'groups',
                'schedule' => 'draft',
            ])
            ->assertRedirect(route('user.campaigns.create'))
            ->assertSessionHasErrors($provider === 'email' ? 'message_subject' : 'message_body');
    }
});

it('only exposes campaign enabled providers in the campaign builder', function (): void {
    [$user, $workspace] = campaignUserContext();

    foreach (['whatsapp', 'telegram', 'email', 'sms', 'messenger', 'instagram', 'threads'] as $provider) {
        campaignChannel($workspace->id, $provider);
    }

    $data = app(CampaignService::class)->builderData($user);

    expect($data['providers'])->toBe(['whatsapp', 'telegram', 'email', 'sms'])
        ->and($data['channels']->pluck('provider')->values()->all())->toBe(['whatsapp', 'telegram', 'email', 'sms']);
});

it('renders the campaign builder with connected icon steps instead of numbered markers', function (): void {
    [$user, $workspace] = campaignUserContext();
    campaignChannel($workspace->id, 'whatsapp');

    $this->actingAs($user)
        ->get(route('user.campaigns.create'))
        ->assertOk()
        ->assertSee('data-campaign-stepper', false)
        ->assertSee('left-[calc(50%+1.25rem)]', false)
        ->assertSee('ph-gear-six', false)
        ->assertSee('ph-chat-circle-text', false)
        ->assertDontSee('absolute left-8 right-8 top-5', false)
        ->assertDontSee('<span class="step-chip">1</span>', false)
        ->assertDontSee('<span class="step-chip">5</span>', false);
});

it('skips telegram campaign contacts without a provider identity', function (): void {
    [, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'telegram');
    $contact = campaignContact($workspace->id);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'uuid' => (string) Str::uuid(),
        'name' => 'Telegram identity check',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'message_body' => 'Hello',
        'send_rate_per_minute' => 60,
    ]);

    $recipient = app(CampaignRecipientService::class)->createForContact($campaign, $contact);

    expect($recipient->status)->toBe(CampaignRecipientStatus::SkippedPolicy)
        ->and($recipient->recipient_address)->toBeNull()
        ->and($recipient->error_code)->toBe('telegram_opt_in_missing');
});

it('saves drafts without preparing recipients and launches them on update', function (): void {
    Queue::fake();

    [$user, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'email');
    $contact = campaignContact($workspace->id);
    $group = campaignGroupWithContacts($workspace->id, [$contact]);

    $this->actingAs($user)
        ->post(route('user.campaigns.store'), [
            'name' => 'Draft newsletter',
            'provider' => 'email',
            'channel_account_id' => $channel->id,
            'message_type' => 'custom',
            'audience_type' => 'groups',
            'audience_ids' => [$group->id],
            'message_subject' => 'Hello {{name}}',
            'message_body' => '<p>Welcome {{first_name}}</p>',
            'schedule' => 'draft',
        ])
        ->assertRedirect();

    Queue::assertNotPushed(PrepareCampaignRecipientsJob::class);

    $campaign = Campaign::query()->where('name', 'Draft newsletter')->firstOrFail();
    expect($campaign->status)->toBe(CampaignStatus::Draft);

    $this->actingAs($user)
        ->put(route('user.campaigns.update', $campaign), [
            'name' => 'Draft newsletter',
            'provider' => 'email',
            'channel_account_id' => $channel->id,
            'message_type' => 'custom',
            'audience_type' => 'groups',
            'audience_ids' => [$group->id],
            'message_subject' => 'Hello {{name}}',
            'message_body' => '<p>Welcome {{first_name}}</p>',
            'schedule' => 'now',
        ])
        ->assertRedirect(route('user.campaigns.report', $campaign));

    Queue::assertPushed(PrepareCampaignRecipientsJob::class, fn (PrepareCampaignRecipientsJob $job): bool => $job->campaignId === $campaign->id);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sending);
});

it('re-runs a completed campaign without creating a duplicate campaign record', function (): void {
    Queue::fake();

    [$user, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'email');
    $contact = campaignContact($workspace->id, ['email' => 'rerun@example.com']);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'email',
        'uuid' => (string) Str::uuid(),
        'name' => 'Rerun campaign',
        'status' => CampaignStatus::Completed->value,
        'audience_type' => 'groups',
        'message_body' => 'Hello again',
        'send_rate_per_minute' => 60,
        'total_recipients' => 1,
        'sent_count' => 1,
        'delivered_count' => 1,
        'completed_at' => now(),
    ]);
    CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'email',
        'uuid' => (string) Str::uuid(),
        'recipient_address' => 'rerun@example.com',
        'status' => CampaignRecipientStatus::Sent->value,
        'provider_message_id' => 'msg-1',
        'sent_at' => now(),
        'delivered_at' => now(),
    ]);

    $beforeCampaignCount = Campaign::query()->count();

    $this->actingAs($user)
        ->post(route('user.campaigns.re-run', $campaign))
        ->assertRedirect(route('user.campaigns.report', $campaign))
        ->assertSessionHas('status', 'Campaign re-run started.');

    Queue::assertPushed(PrepareCampaignRecipientsJob::class, fn (PrepareCampaignRecipientsJob $job): bool => $job->campaignId === $campaign->id);

    expect(Campaign::query()->count())->toBe($beforeCampaignCount)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Sending)
        ->and($campaign->fresh()->recipients()->count())->toBe(0)
        ->and($campaign->fresh()->sent_count)->toBe(0)
        ->and($campaign->fresh()->completed_at)->toBeNull();
});

it('prepares recipients idempotently and records skip statuses', function (): void {
    Queue::fake();

    [$user, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'email');
    $sendable = campaignContact($workspace->id, ['email' => 'sendable@example.com']);
    $optedOut = campaignContact($workspace->id, ['email' => 'optout@example.com', 'opt_in_status' => ContactOptInStatus::Unsubscribed->value]);
    $blocked = campaignContact($workspace->id, ['email' => 'blocked@example.com', 'blocked_at' => now()]);
    $invalid = campaignContact($workspace->id, ['email' => 'not-an-email']);
    $group = campaignGroupWithContacts($workspace->id, [$sendable, $optedOut, $blocked, $invalid]);

    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'email',
        'uuid' => (string) Str::uuid(),
        'name' => 'Recipient prep',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'audience_ids' => [$group->id],
        'message_subject' => 'Hello',
        'message_body' => 'Body',
        'send_rate_per_minute' => 60,
    ]);

    app(CampaignService::class)->prepareRecipients($campaign);
    app(CampaignService::class)->prepareRecipients($campaign->fresh());

    expect($campaign->recipients()->count())->toBe(4)
        ->and($campaign->recipients()->where('status', CampaignRecipientStatus::Queued->value)->count())->toBe(1)
        ->and($campaign->recipients()->where('status', CampaignRecipientStatus::SkippedOptOut->value)->count())->toBe(1)
        ->and($campaign->recipients()->where('status', CampaignRecipientStatus::SkippedBlocked->value)->count())->toBe(1)
        ->and($campaign->recipients()->where('status', CampaignRecipientStatus::SkippedInvalid->value)->count())->toBe(1);

    Queue::assertPushed(SendCampaignRecipientJob::class, 1);
});

it('pauses resumes and cancels queued recipients', function (): void {
    Queue::fake();

    [$user, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'sms');
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'sms',
        'uuid' => (string) Str::uuid(),
        'name' => 'Lifecycle campaign',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'message_body' => 'Hello',
        'send_rate_per_minute' => 60,
    ]);
    $contact = campaignContact($workspace->id);
    $recipient = CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'sms',
        'uuid' => (string) Str::uuid(),
        'to' => $contact->phone,
        'recipient_address' => $contact->phone,
        'status' => CampaignRecipientStatus::Queued->value,
        'queued_at' => now(),
    ]);

    $service = app(CampaignService::class);
    $service->pause($campaign);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Paused);

    $service->resume($campaign->fresh());
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Sending);
    Queue::assertPushed(SendCampaignRecipientJob::class, fn (SendCampaignRecipientJob $job): bool => $job->recipientId === $recipient->id);

    $service->cancel($campaign->fresh());
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Cancelled)
        ->and($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Failed);
});

it('shows visible edit and delete actions on the campaign index', function (): void {
    [$user, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) Str::uuid(),
        'name' => 'Action campaign',
        'status' => CampaignStatus::Draft->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.campaigns.index'))
        ->assertOk()
        ->assertSee(route('user.campaigns.edit', $campaign), false)
        ->assertSee(route('user.campaigns.destroy', $campaign), false)
        ->assertSee('ph-pencil-simple', false)
        ->assertSee('ph-trash', false);
});

it('applies webhook status events to recipients messages and campaign stats', function (): void {
    [$user, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) Str::uuid(),
        'name' => 'Webhook campaign',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    $recipient = CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) Str::uuid(),
        'to' => $contact->phone,
        'recipient_address' => $contact->phone,
        'status' => CampaignRecipientStatus::Sent->value,
        'provider_message_id' => 'wamid.test',
        'sent_at' => now(),
    ]);
    Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'contact_id' => $contact->id,
        'direction' => 'outbound',
        'type' => 'template',
        'status' => 'sent',
        'provider_message_id' => 'wamid.test',
        'campaign_id' => $campaign->id,
        'whatsapp_message_id' => 'wamid.test',
    ]);
    $event = ChannelWebhookEvent::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'event_type' => 'message.status',
        'payload_hash' => sha1('webhook-status'),
        'payload' => [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => $channel->provider_phone_id],
                        'statuses' => [['id' => 'wamid.test', 'status' => 'delivered']],
                    ],
                ]],
            ]],
        ],
        'headers' => [],
        'status' => ChannelWebhookEventStatus::Pending->value,
    ]);

    (new ProcessChannelWebhookJob($event->id))->handle(app(ChannelManager::class), app(AutomationDispatcher::class), app(AutoReplyService::class));
    app(CampaignReportService::class)->refresh($campaign->fresh());

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Delivered)
        ->and(Message::query()->where('provider_message_id', 'wamid.test')->first()->status->value)->toBe('delivered')
        ->and($campaign->fresh()->delivered_count)->toBe(1);
});

it('builds whatsapp template payloads without resending template definition components', function (): void {
    [, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'hello_world',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [
            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Hello World'],
            ['type' => 'BODY', 'text' => 'Welcome and congratulations!!'],
            ['type' => 'FOOTER', 'text' => 'WhatsApp Business Platform sample message'],
        ],
    ]);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'message_type' => 'template',
        'message_template_id' => $template->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Template payload campaign',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    $recipient = CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) Str::uuid(),
        'to' => $contact->phone,
        'recipient_address' => $contact->phone,
        'status' => CampaignRecipientStatus::Queued->value,
        'queued_at' => now(),
    ]);

    $payload = app(CampaignRecipientService::class)->buildPayload($campaign->fresh(), $recipient->fresh());

    expect($payload['meta_payload']['template'])
        ->toBe([
            'name' => 'hello_world',
            'language' => ['code' => 'en_US'],
        ])
        ->and($payload['components'])->toBe([]);
});

it('builds whatsapp template runtime parameters from approved template formats', function (): void {
    [, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id, [
        'name' => 'Ada Lovelace',
        'phone' => '+15555550123',
    ]);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'order_ready',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [
            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Hi {{full_name}}', 'example' => ['header_text' => ['Grace']]],
            ['type' => 'BODY', 'text' => 'Your order {{custom.order_id}} is ready at {{custom.pickup_time}}.', 'example' => ['body_text' => [['A-100', '10:00 AM']]]],
            ['type' => 'BUTTONS', 'buttons' => [
                ['type' => 'URL', 'text' => 'Track order', 'url' => 'https://example.com/orders/{{custom.order_id}}', 'example' => ['A-100']],
            ]],
        ],
    ]);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'message_type' => 'template',
        'message_template_id' => $template->id,
        'variables' => [],
        'uuid' => (string) Str::uuid(),
        'name' => 'Runtime template payload campaign',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    $recipient = CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) Str::uuid(),
        'to' => $contact->phone,
        'recipient_address' => $contact->phone,
        'status' => CampaignRecipientStatus::Queued->value,
        'queued_at' => now(),
    ]);

    $payload = app(CampaignRecipientService::class)->buildPayload($campaign->fresh(), $recipient->fresh());

    expect($payload['meta_payload']['template'])->toBe([
        'name' => 'order_ready',
        'language' => ['code' => 'en_US'],
        'components' => [
            [
                'type' => 'header',
                'parameters' => [
                    ['type' => 'text', 'text' => 'Ada Lovelace'],
                ],
            ],
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => 'A-100'],
                    ['type' => 'text', 'text' => '10:00 AM'],
                ],
            ],
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => 'A-100'],
                ],
            ],
        ],
    ]);
});

it('resolves whatsapp header media from the stored media record when no url is persisted', function (): void {
    [, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id, [
        'name' => 'Ada Lovelace',
        'phone' => '+15555550124',
    ]);
    $media = Media::query()->create([
        'name' => 'campaign-header',
        'file_name' => 'campaign-header.png',
        'original_name' => 'campaign-header.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'type' => 'image',
        'size' => 1200,
        'disk' => 'public',
        'path' => 'campaign-headers/campaign-header.png',
        'uploaded_by' => null,
    ]);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'header_media_only',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [
            ['type' => 'HEADER', 'format' => 'IMAGE', 'media_id' => $media->id],
            ['type' => 'BODY', 'text' => 'Hello there'],
            ['type' => 'FOOTER', 'text' => 'Footer note'],
            ['type' => 'BUTTONS', 'buttons' => [
                ['type' => 'URL', 'text' => 'View', 'url' => 'https://example.com'],
            ]],
        ],
    ]);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'message_type' => 'template',
        'message_template_id' => $template->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Media header template campaign',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    $recipient = CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) Str::uuid(),
        'to' => $contact->phone,
        'recipient_address' => $contact->phone,
        'status' => CampaignRecipientStatus::Queued->value,
        'queued_at' => now(),
    ]);

    $payload = app(CampaignRecipientService::class)->buildPayload($campaign->fresh(), $recipient->fresh());

    expect($payload['meta_payload']['template']['components'][0]['type'])->toBe('header')
        ->and(data_get($payload, 'meta_payload.template.components.0.parameters.0.image.link'))->toBeString()
        ->and($payload['components'])->toBe([
            [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => ['link' => data_get($payload, 'meta_payload.template.components.0.parameters.0.image.link')],
                    ],
                ],
            ],
        ]);
});

it('builds whatsapp runtime parameters from the approved meta schema for the selected waba', function (): void {
    [, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'whatsapp');
    $contact = campaignContact($workspace->id, [
        'name' => 'Ada Lovelace',
        'phone' => '+15555550125',
        'custom_fields' => [
            'order_id' => 'A-100',
            'pickup_time' => '10:00 AM',
        ],
    ]);
    $media = Media::query()->create([
        'name' => 'approved-header',
        'file_name' => 'approved-header.png',
        'original_name' => 'approved-header.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'type' => 'image',
        'size' => 1200,
        'disk' => 'public',
        'path' => 'campaign-headers/approved-header.png',
        'uploaded_by' => null,
    ]);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'approved_schema_template',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [
            ['type' => 'HEADER', 'format' => 'IMAGE', 'media_id' => $media->id],
            ['type' => 'BODY', 'text' => 'Your order {{custom.order_id}} is ready at {{custom.pickup_time}}. Extra local note {{first_name}}.', 'example' => ['body_text' => [['A-100', '10:00 AM', 'Ada']]]],
            ['type' => 'BUTTONS', 'buttons' => [
                ['type' => 'URL', 'text' => 'Track order', 'url' => 'https://example.com/orders/{{custom.order_id}}'],
                ['type' => 'URL', 'text' => 'Static link', 'url' => 'https://example.com/static/{{custom.order_id}}'],
            ]],
        ],
        'submission_payload' => [
            'name' => 'approved_schema_template',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'components' => [
                ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => ['4:test-handle']]],
                ['type' => 'BODY', 'text' => 'Your order {{1}} is ready at {{2}}.'],
                ['type' => 'BUTTONS', 'buttons' => [
                    ['type' => 'URL', 'text' => 'Track order', 'url' => 'https://example.com/orders/{{1}}'],
                    ['type' => 'URL', 'text' => 'Static link', 'url' => 'https://example.com/static'],
                ]],
            ],
        ],
    ]);
    MessageTemplateSubmission::query()->create([
        'workspace_id' => $workspace->id,
        'message_template_id' => $template->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'provider_account_id' => $channel->provider_account_id,
        'whatsapp_template_id' => 'meta-template-approved',
        'status' => 'approved',
        'submission_payload' => $template->submission_payload,
        'meta_response' => ['id' => 'meta-template-approved'],
        'synced_at' => now(),
    ]);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'message_type' => 'template',
        'message_template_id' => $template->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Approved schema campaign',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    $recipient = CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) Str::uuid(),
        'to' => $contact->phone,
        'recipient_address' => $contact->phone,
        'status' => CampaignRecipientStatus::Queued->value,
        'queued_at' => now(),
    ]);

    $payload = app(CampaignRecipientService::class)->buildPayload($campaign->fresh(), $recipient->fresh());

    expect($payload['meta_payload']['template']['components'])->toHaveCount(3)
        ->and(data_get($payload, 'meta_payload.template.components.0.parameters.0.image.link'))->toBeString()
        ->and(data_get($payload, 'meta_payload.template.components.1.parameters'))->toBe([
            ['type' => 'text', 'text' => 'A-100'],
            ['type' => 'text', 'text' => '10:00 AM'],
        ])
        ->and(data_get($payload, 'meta_payload.template.components.2'))->toBe([
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [
                ['type' => 'text', 'text' => 'A-100'],
            ],
        ]);
});

it('builds telegram template campaign payloads with named shortcodes', function (): void {
    [, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'telegram');
    $contact = campaignContact($workspace->id, [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'custom_fields' => ['website' => 'ada.dev', 'plan' => 'Pro'],
    ]);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'provider_contact_id' => 'telegram-ada',
        'address' => '123456',
        'identity_type' => 'telegram_user_id',
    ]);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'telegram',
        'name' => 'telegram_welcome',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [
            ['type' => 'BODY', 'text' => 'Hi {{full_name}}, your {{custom.plan}} workspace is ready. Website: {{website}}'],
            ['type' => 'BUTTONS', 'buttons' => [
                ['type' => 'URL', 'text' => 'Open', 'url' => 'https://{{website}}'],
                ['type' => 'CALLBACK', 'text' => 'Start', 'callback_data' => 'start_{{phone}}'],
            ]],
        ],
    ]);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'telegram',
        'message_type' => 'template',
        'message_template_id' => $template->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Telegram template payload campaign',
        'status' => CampaignStatus::Sending->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    $recipient = app(CampaignRecipientService::class)->createForContact($campaign, $contact);

    $payload = app(CampaignRecipientService::class)->buildPayload($campaign->fresh(), $recipient->fresh());

    expect($payload)->toMatchArray([
        'type' => 'text',
        'body' => 'Hi Ada Lovelace, your Pro workspace is ready. Website: ada.dev',
        'parse_mode' => 'HTML',
        'chat_id' => '123456',
        'buttons' => [
            ['text' => 'Open', 'url' => 'https://ada.dev'],
            ['text' => 'Start', 'callback_data' => 'start_'.$contact->phone],
        ],
    ]);
});

it('blocks template campaigns when the template provider does not match the sender', function (): void {
    [$user, $workspace] = campaignUserContext();
    $telegram = campaignChannel($workspace->id, 'telegram');
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'whatsapp_only',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [['type' => 'BODY', 'text' => 'WhatsApp only']],
    ]);

    $this->actingAs($user)
        ->from(route('user.campaigns.create'))
        ->post(route('user.campaigns.store'), [
            'name' => 'Wrong provider campaign',
            'channel_account_id' => $telegram->id,
            'message_type' => 'template',
            'message_template_id' => $template->id,
            'audience_type' => 'groups',
            'schedule' => 'draft',
        ])
        ->assertRedirect(route('user.campaigns.create'))
        ->assertSessionHasErrors('message_template_id');
});

it('exports campaign recipient reports as csv', function (): void {
    [$user, $workspace] = campaignUserContext();
    $channel = campaignChannel($workspace->id, 'email');
    $contact = campaignContact($workspace->id, ['email' => 'csv@example.com']);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'email',
        'uuid' => (string) Str::uuid(),
        'name' => 'CSV campaign',
        'status' => CampaignStatus::Completed->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'email',
        'uuid' => (string) Str::uuid(),
        'recipient_address' => 'csv@example.com',
        'status' => CampaignRecipientStatus::Sent->value,
        'provider_message_id' => 'email-1',
        'sent_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('user.campaigns.report', $campaign))
        ->assertOk()
        ->assertSee('CSV campaign')
        ->assertSee('Re-run')
        ->assertSee('This campaign completed successfully.')
        ->assertDontSee('Export CSV')
        ->assertDontSee('Duplicate');

    $response = $this->actingAs($user)
        ->get(route('user.campaigns.export', $campaign))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('Contact,Address,Status')
        ->toContain('csv@example.com')
        ->toContain('email-1');
});
