<?php

use App\Models\User;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\Automations\Services\AutomationRunner;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\Crm\Enums\CrmLeadStatus;
use App\Modules\Crm\Enums\CrmTaskStatus;
use App\Modules\Crm\Jobs\SendCrmTaskRemindersJob;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Models\CrmPipeline;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\Crm\Services\CRMLeadService;
use App\Modules\Crm\Services\LeadAssignmentService;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Crm\Services\TaskService;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Jobs\ProcessChannelWebhookJob;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\SystemNotifications\Models\SystemNotification;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function crmTestContext(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'CRM Customer',
        'phone' => '+15555550123',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'provider_conversation_id' => '15555550123',
        'contact_id' => $contact->id,
        'status' => 'open',
        'labels' => [],
    ]);

    return [$user, $workspace, $contact, $conversation];
}

it('resolves CRM and contact automation services without circular dependencies', function (): void {
    expect(app(ContactService::class))->toBeInstanceOf(ContactService::class)
        ->and(app(AutomationDispatcher::class))->toBeInstanceOf(AutomationDispatcher::class)
        ->and(app(AutomationRunner::class))->toBeInstanceOf(AutomationRunner::class)
        ->and(app(CRMLeadService::class))->toBeInstanceOf(CRMLeadService::class);
});

it('creates one open lead per contact and pipeline and records CRM work', function (): void {
    [$user, $workspace, $contact, $conversation] = crmTestContext();
    $pipeline = app(PipelineService::class)->ensureDefaultForWorkspace($workspace->id);
    $leadService = app(CRMLeadService::class);

    $lead = $leadService->createOrUpdate($workspace->id, $contact->id, [
        'conversation_id' => $conversation->id,
        'title' => 'WhatsApp opportunity',
        'source' => 'whatsapp',
    ], $user);
    $sameLead = $leadService->createOrUpdate($workspace->id, $contact->id, [
        'pipeline_id' => $pipeline->id,
        'value' => 2500,
    ], $user);
    $moved = $leadService->moveStage($workspace->id, $lead->id, $pipeline->stages[1]->id, $user);
    $note = $leadService->addNote($workspace->id, $lead->id, 'Customer requested a proposal.', $user);
    $task = app(TaskService::class)->create($workspace->id, [
        'lead_id' => $lead->id,
        'contact_id' => $contact->id,
        'assigned_to' => $user->id,
        'title' => 'Send proposal',
        'priority' => 'high',
        'due_at' => now()->addDay(),
    ], $user);
    $laterTask = app(TaskService::class)->create($workspace->id, [
        'lead_id' => $lead->id,
        'contact_id' => $contact->id,
        'assigned_to' => $user->id,
        'title' => 'Check decision',
        'priority' => 'normal',
        'due_at' => now()->addDays(2),
    ], $user);
    app(TaskService::class)->updateStatus($workspace->id, $task->id, CrmTaskStatus::Completed, $user);

    expect($sameLead->id)->toBe($lead->id)
        ->and(CrmLead::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and($moved->stage_id)->toBe($pipeline->stages[1]->id)
        ->and($note->type->value)->toBe('note')
        ->and($task->status)->toBe(CrmTaskStatus::Pending)
        ->and($lead->fresh()->next_follow_up_at?->equalTo($laterTask->due_at))->toBeTrue()
        ->and(CrmActivity::query()->where('workspace_id', $workspace->id)->where('lead_id', $lead->id)->count())->toBeGreaterThanOrEqual(6);
});

it('creates a lead using the selected stage pipeline when the submitted pipeline is stale', function (): void {
    [$user, $workspace, $contact, $conversation] = crmTestContext();
    $defaultPipeline = app(PipelineService::class)->ensureDefaultForWorkspace($workspace->id);
    $targetPipeline = app(PipelineService::class)->create($workspace->id, ['name' => 'Imported Pipeline']);
    $targetStage = app(PipelineService::class)->createStage($workspace->id, $targetPipeline->id, ['name' => 'Imported Stage']);

    $lead = app(CRMLeadService::class)->createOrUpdate($workspace->id, $contact->id, [
        'conversation_id' => $conversation->id,
        'pipeline_id' => $defaultPipeline->id,
        'stage_id' => $targetStage->id,
        'title' => 'Inbox lead',
    ], $user);

    expect($lead->pipeline_id)->toBe($targetPipeline->id)
        ->and($lead->stage_id)->toBe($targetStage->id)
        ->and($lead->title)->toBe('Inbox lead');
});

it('assigns only active workspace members and supports won lost history', function (): void {
    [$owner, $workspace, $contact] = crmTestContext();
    $agent = User::factory()->create(['email_verified_at' => now()]);
    $workspace->members()->attach($agent->id, ['role' => WorkspaceMemberRole::Staff->value, 'status' => WorkspaceMemberStatus::Active->value]);
    $outsider = User::factory()->create();
    $lead = app(CRMLeadService::class)->createOrUpdate($workspace->id, $contact->id, []);

    $assigned = app(LeadAssignmentService::class)->assign($workspace->id, $lead->id, $agent->id, $owner);
    $won = app(CRMLeadService::class)->markWon($workspace->id, $lead->id, $owner);
    $newLead = app(CRMLeadService::class)->createOrUpdate($workspace->id, $contact->id, []);
    $lost = app(CRMLeadService::class)->markLost($workspace->id, $newLead->id, 'Budget', $owner);

    expect($assigned->assigned_to)->toBe($agent->id)
        ->and($won->status)->toBe(CrmLeadStatus::Won)
        ->and($newLead->id)->not->toBe($lead->id)
        ->and($lost->status)->toBe(CrmLeadStatus::Lost)
        ->and($lost->lost_reason)->toBe('Budget');

    app(LeadAssignmentService::class)->assign($workspace->id, $newLead->id, $outsider->id, $owner);
})->throws(ModelNotFoundException::class);

it('does not expose or mutate CRM records from another workspace', function (): void {
    [$user] = crmTestContext();
    [, , $otherContact, $otherConversation] = crmTestContext();

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations.crm', $otherConversation))
        ->assertNotFound();

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.crm.leads.store'), ['contact_id' => $otherContact->id])
        ->assertNotFound();

    expect(CrmLead::query()->where('contact_id', $otherContact->id)->exists())->toBeFalse();
});

it('loads workspace CRM profile data in the contact sidebar', function (): void {
    [$user, $workspace, $contact, $conversation] = crmTestContext();
    $tag = ContactTag::query()->create(['workspace_id' => $workspace->id, 'name' => 'VIP', 'slug' => 'vip']);
    $contact->tags()->attach($tag);
    $lead = app(CRMLeadService::class)->createOrUpdate($workspace->id, $contact->id, ['conversation_id' => $conversation->id, 'title' => 'Sidebar opportunity']);
    app(CRMLeadService::class)->addNote($workspace->id, $lead->id, 'Sidebar note', $user);
    app(TaskService::class)->create($workspace->id, ['lead_id' => $lead->id, 'contact_id' => $contact->id, 'assigned_to' => $user->id, 'title' => 'Sidebar task', 'priority' => 'normal', 'due_at' => now()->addHour()], $user);
    Message::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'conversation_id' => $conversation->id,
        'contact_id' => $contact->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'Sidebar message',
        'status' => 'received',
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations.crm', $conversation))
        ->assertOk()
        ->assertJsonPath('crm.contact.id', $contact->id)
        ->assertJsonPath('crm.contact.tags.0.name', 'VIP')
        ->assertJsonPath('crm.current_lead.title', 'Sidebar opportunity')
        ->assertJsonPath('crm.tasks.0.title', 'Sidebar task')
        ->assertJsonFragment(['description' => 'Sidebar note'])
        ->assertJsonFragment(['description' => 'Sidebar message']);
});

it('validates duplicate stage names in the same pipeline before saving', function (): void {
    [$user, $workspace] = crmTestContext();
    Permission::findOrCreate('crm.manage', 'web');
    $user->givePermissionTo('crm.manage');
    $pipeline = app(PipelineService::class)->ensureDefaultForWorkspace($workspace->id);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.crm.index'))
        ->post(route('user.crm.stages.store', $pipeline), [
            'name' => 'New',
            'color' => '#075E54',
        ])
        ->assertRedirect(route('user.crm.index'))
        ->assertSessionHasErrors('name');

    expect($pipeline->stages()->where('name', 'New')->count())->toBe(1);
});

it('creates custom pipelines with default stages and opens the new board', function (): void {
    [$user, $workspace] = crmTestContext();
    Permission::findOrCreate('crm.manage', 'web');
    $user->givePermissionTo('crm.manage');

    $response = $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.crm.index'))
        ->post(route('user.crm.pipelines.store'), [
            'name' => 'Enterprise Sales',
        ]);

    $pipeline = CrmPipeline::query()
        ->where('workspace_id', $workspace->id)
        ->where('name', 'Enterprise Sales')
        ->firstOrFail();

    $response->assertRedirect(route('user.crm.index', ['pipeline' => $pipeline->id]));

    expect($pipeline->stages()->orderBy('position')->pluck('name')->all())->toBe([
        'New',
        'Contacted',
        'Qualified',
        'Proposal',
    ]);
});

it('validates duplicate pipeline names before saving', function (): void {
    [$user, $workspace] = crmTestContext();
    Permission::findOrCreate('crm.manage', 'web');
    $user->givePermissionTo('crm.manage');
    app(PipelineService::class)->ensureDefaultForWorkspace($workspace->id);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.crm.index'))
        ->post(route('user.crm.pipelines.store'), [
            'name' => 'Sales Pipeline',
        ])
        ->assertRedirect(route('user.crm.index'))
        ->assertSessionHasErrors('name');

    expect(CrmPipeline::query()->where('workspace_id', $workspace->id)->where('name', 'Sales Pipeline')->count())->toBe(1);
});

it('redirects to the default CRM board after deleting a pipeline', function (): void {
    [$user, $workspace] = crmTestContext();
    Permission::findOrCreate('crm.manage', 'web');
    $user->givePermissionTo('crm.manage');
    $pipeline = app(PipelineService::class)->create($workspace->id, ['name' => 'Temporary Pipeline']);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.crm.index', ['pipeline' => $pipeline->id]))
        ->delete(route('user.crm.pipelines.destroy', $pipeline))
        ->assertRedirect(route('user.crm.index'));

    expect(CrmPipeline::query()->whereKey($pipeline->id)->exists())->toBeFalse();
});

it('turns an attributed WhatsApp campaign reply into a tagged CRM lead when enabled', function (): void {
    [, $workspace, $contact] = crmTestContext();
    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'WhatsApp',
        'status' => 'connected',
        'credentials' => ['access_token' => 'test'],
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-1',
        'connected_at' => now(),
    ]);
    ContactProviderIdentity::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'provider_contact_id' => '15555550123',
        'address' => $contact->phone,
    ]);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) str()->uuid(),
        'name' => 'CRM Reply Campaign',
        'status' => CampaignStatus::Completed->value,
        'settings' => ['crm_create_lead_on_reply' => true],
    ]);
    $recipient = CampaignRecipient::query()->create([
        'workspace_id' => $workspace->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'uuid' => (string) str()->uuid(),
        'status' => CampaignRecipientStatus::Sent->value,
        'provider_message_id' => 'wamid.outbound',
        'sent_at' => now(),
    ]);
    $payload = ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'waba-1', 'changes' => [['field' => 'messages', 'value' => ['metadata' => ['phone_number_id' => 'phone-1'], 'messages' => [['from' => '15555550123', 'id' => 'wamid.reply', 'type' => 'text', 'text' => ['body' => 'I am interested']]]]]]]]];
    $event = ChannelWebhookEvent::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'event_type' => 'message.received',
        'provider_event_id' => 'crm-reply-event',
        'payload_hash' => sha1('crm-reply-event'),
        'payload' => $payload,
        'status' => ChannelWebhookEventStatus::Pending->value,
    ]);

    app()->call([(new ProcessChannelWebhookJob($event->id)), 'handle']);

    $lead = CrmLead::query()->where('workspace_id', $workspace->id)->where('contact_id', $contact->id)->firstOrFail();
    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Replied)
        ->and($contact->fresh()->tags()->where('slug', 'campaign-replied')->exists())->toBeTrue()
        ->and($lead->source->value)->toBe('campaign')
        ->and($lead->campaign_id)->toBe($campaign->id);
});

it('tags campaign replies without creating a lead when CRM creation is disabled', function (): void {
    [, $workspace, $contact, $conversation] = crmTestContext();
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'uuid' => (string) str()->uuid(),
        'name' => 'Tag only campaign',
        'status' => CampaignStatus::Completed->value,
        'settings' => ['crm_create_lead_on_reply' => false],
    ]);

    $lead = app(CRMLeadService::class)->handleCampaignReply($workspace->id, $contact->id, $conversation->id, $campaign);

    expect($lead)->toBeNull()
        ->and($contact->fresh()->tags()->where('slug', 'campaign-replied')->exists())->toBeTrue()
        ->and(CrmLead::query()->where('workspace_id', $workspace->id)->where('contact_id', $contact->id)->exists())->toBeFalse()
        ->and(CrmActivity::query()->where('workspace_id', $workspace->id)->where('contact_id', $contact->id)->where('type', 'campaign_reply')->exists())->toBeTrue();
});

it('creates CRM leads through automation without touching generated prospects', function (): void {
    [, $workspace, $contact, $conversation] = crmTestContext();
    $automation = Automation::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'CRM create lead',
        'nodes' => [['id' => 'action-1', 'type' => 'action', 'kind' => 'create_lead', 'data' => ['title' => 'Automated CRM lead'], 'ports' => [['id' => 'in', 'direction' => 'input'], ['id' => 'default', 'direction' => 'output']]]],
        'edges' => [],
        'is_active' => true,
    ]);
    $run = AutomationRun::query()->create(['workspace_id' => $workspace->id, 'automation_id' => $automation->id, 'status' => 'running', 'started_at' => now()]);

    app(AutomationRunner::class)->execute($automation->id, $run->id, 'action-1', ['workspace_id' => $workspace->id, 'contact_id' => $contact->id, 'conversation_id' => $conversation->id]);

    expect(CrmLead::query()->where('workspace_id', $workspace->id)->where('source', 'automation')->exists())->toBeTrue()
        ->and(DB::table('leads')->where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('executes workspace-scoped CRM automation actions', function (): void {
    [$owner, $workspace, $contact, $conversation] = crmTestContext();
    $agent = User::factory()->create(['email_verified_at' => now()]);
    $workspace->members()->attach($agent->id, ['role' => WorkspaceMemberRole::Staff->value, 'status' => WorkspaceMemberStatus::Active->value]);
    $pipeline = app(PipelineService::class)->ensureDefaultForWorkspace($workspace->id);
    $tag = ContactTag::query()->create(['workspace_id' => $workspace->id, 'name' => 'Automation VIP', 'slug' => 'automation-vip']);
    $lead = app(CRMLeadService::class)->createOrUpdate($workspace->id, $contact->id, ['conversation_id' => $conversation->id]);
    $nodes = [
        ['id' => 'tag', 'type' => 'action', 'kind' => 'add_contact_tag', 'data' => ['tag_id' => $tag->id]],
        ['id' => 'stage', 'type' => 'action', 'kind' => 'update_lead_stage', 'data' => ['stage_id' => $pipeline->stages[1]->id]],
        ['id' => 'task', 'type' => 'action', 'kind' => 'create_task', 'data' => ['title' => 'Automation follow-up', 'assigned_to' => $agent->id, 'due_in_minutes' => 30]],
        ['id' => 'assign', 'type' => 'action', 'kind' => 'assign_conversation', 'data' => ['agent_id' => $agent->id]],
        ['id' => 'won', 'type' => 'action', 'kind' => 'mark_lead_won', 'data' => []],
    ];
    $automation = Automation::query()->create(['workspace_id' => $workspace->id, 'name' => 'CRM actions', 'nodes' => $nodes, 'edges' => [], 'is_active' => true]);
    $runner = app(AutomationRunner::class);
    $context = ['workspace_id' => $workspace->id, 'contact_id' => $contact->id, 'conversation_id' => $conversation->id, 'crm_lead_id' => $lead->id];

    foreach (array_column($nodes, 'id') as $nodeId) {
        $run = AutomationRun::query()->create(['workspace_id' => $workspace->id, 'automation_id' => $automation->id, 'status' => 'running', 'started_at' => now()]);
        $runner->execute($automation->id, $run->id, $nodeId, $context);
        expect($run->fresh()->status)->toBe('completed');
    }

    expect($contact->fresh()->tags()->whereKey($tag->id)->exists())->toBeTrue()
        ->and($lead->fresh()->stage_id)->toBe($pipeline->stages[1]->id)
        ->and($lead->fresh()->assigned_to)->toBe($agent->id)
        ->and($lead->fresh()->status)->toBe(CrmLeadStatus::Won)
        ->and(CrmTask::query()->where('workspace_id', $workspace->id)->where('title', 'Automation follow-up')->exists())->toBeTrue();

    $lostLead = app(CRMLeadService::class)->createOrUpdate($workspace->id, $contact->id, []);
    $automation->update(['nodes' => [['id' => 'lost', 'type' => 'action', 'kind' => 'mark_lead_lost', 'data' => ['lost_reason' => 'No budget']]]]);
    $lostRun = AutomationRun::query()->create(['workspace_id' => $workspace->id, 'automation_id' => $automation->id, 'status' => 'running', 'started_at' => now()]);
    $runner->execute($automation->id, $lostRun->id, 'lost', array_merge($context, ['crm_lead_id' => $lostLead->id]));

    expect($lostLead->fresh()->status)->toBe(CrmLeadStatus::Lost)
        ->and($lostLead->fresh()->lost_reason)->toBe('No budget');
});

it('rejects CRM automation resources from another workspace', function (): void {
    [, $workspace, $contact, $conversation] = crmTestContext();
    [$otherUser, $otherWorkspace] = crmTestContext();
    $otherPipeline = app(PipelineService::class)->ensureDefaultForWorkspace($otherWorkspace->id);
    $otherTag = ContactTag::query()->create(['workspace_id' => $otherWorkspace->id, 'name' => 'Foreign tag', 'slug' => 'foreign-tag']);
    $lead = app(CRMLeadService::class)->createOrUpdate($workspace->id, $contact->id, ['conversation_id' => $conversation->id]);
    $nodes = [
        ['id' => 'foreign-stage', 'type' => 'action', 'kind' => 'update_lead_stage', 'data' => ['stage_id' => $otherPipeline->stages->first()->id]],
        ['id' => 'foreign-tag', 'type' => 'action', 'kind' => 'add_contact_tag', 'data' => ['tag_id' => $otherTag->id]],
        ['id' => 'foreign-agent', 'type' => 'action', 'kind' => 'assign_conversation', 'data' => ['agent_id' => $otherUser->id]],
    ];
    $automation = Automation::query()->create(['workspace_id' => $workspace->id, 'name' => 'Invalid CRM resources', 'nodes' => $nodes, 'edges' => [], 'is_active' => true]);
    $context = ['workspace_id' => $workspace->id, 'contact_id' => $contact->id, 'conversation_id' => $conversation->id, 'crm_lead_id' => $lead->id];

    foreach (array_column($nodes, 'id') as $nodeId) {
        $run = AutomationRun::query()->create(['workspace_id' => $workspace->id, 'automation_id' => $automation->id, 'status' => 'running', 'started_at' => now()]);
        app(AutomationRunner::class)->execute($automation->id, $run->id, $nodeId, $context);
        expect($run->fresh()->status)->toBe('failed');
    }

    expect($contact->fresh()->tags()->whereKey($otherTag->id)->exists())->toBeFalse()
        ->and($conversation->fresh()->assigned_to)->toBeNull()
        ->and($lead->fresh()->stage_id)->not->toBe($otherPipeline->stages->first()->id);
});

it('sends one in-app reminder for each due pending task', function (): void {
    [$user, $workspace, $contact] = crmTestContext();
    $lead = app(CRMLeadService::class)->createOrUpdate($workspace->id, $contact->id, []);
    $due = CrmTask::query()->create(['workspace_id' => $workspace->id, 'lead_id' => $lead->id, 'contact_id' => $contact->id, 'assigned_to' => $user->id, 'title' => 'Call customer', 'status' => 'pending', 'priority' => 'normal', 'due_at' => now()->subMinute()]);
    CrmTask::query()->create(['workspace_id' => $workspace->id, 'lead_id' => $lead->id, 'contact_id' => $contact->id, 'assigned_to' => $user->id, 'title' => 'Future task', 'status' => 'pending', 'priority' => 'normal', 'due_at' => now()->addHour()]);
    CrmTask::query()->create(['workspace_id' => $workspace->id, 'lead_id' => $lead->id, 'contact_id' => $contact->id, 'assigned_to' => $user->id, 'title' => 'Completed task', 'status' => 'completed', 'priority' => 'normal', 'due_at' => now()->subHour(), 'completed_at' => now()]);
    CrmTask::query()->create(['workspace_id' => $workspace->id, 'lead_id' => $lead->id, 'contact_id' => $contact->id, 'assigned_to' => $user->id, 'title' => 'Cancelled task', 'status' => 'cancelled', 'priority' => 'normal', 'due_at' => now()->subHour()]);
    $alreadyReminded = CrmTask::query()->create(['workspace_id' => $workspace->id, 'lead_id' => $lead->id, 'contact_id' => $contact->id, 'assigned_to' => $user->id, 'title' => 'Already reminded', 'status' => 'pending', 'priority' => 'normal', 'due_at' => now()->subHour(), 'reminded_at' => now()->subMinute()]);
    $unassigned = CrmTask::query()->create(['workspace_id' => $workspace->id, 'lead_id' => $lead->id, 'contact_id' => $contact->id, 'assigned_to' => null, 'title' => 'Deleted assignee', 'status' => 'pending', 'priority' => 'normal', 'due_at' => now()->subHour()]);
    [$otherUser, $otherWorkspace, $otherContact] = crmTestContext();
    $otherLead = app(CRMLeadService::class)->createOrUpdate($otherWorkspace->id, $otherContact->id, []);
    CrmTask::query()->create(['workspace_id' => $otherWorkspace->id, 'lead_id' => $otherLead->id, 'contact_id' => $otherContact->id, 'assigned_to' => $otherUser->id, 'title' => 'Other workspace task', 'status' => 'pending', 'priority' => 'normal', 'due_at' => now()->subMinute()]);

    $job = new SendCrmTaskRemindersJob;
    $job->handle(app(SystemNotificationService::class));
    $job->handle(app(SystemNotificationService::class));

    expect($due->fresh()->reminded_at)->not->toBeNull()
        ->and($alreadyReminded->fresh()->reminded_at)->not->toBeNull()
        ->and($unassigned->fresh()->reminded_at)->not->toBeNull()
        ->and(SystemNotification::query()->where('notifiable_id', $user->id)->where('type', 'crm_task_due')->count())->toBe(1)
        ->and(SystemNotification::query()->where('notifiable_id', $otherUser->id)->where('type', 'crm_task_due')->count())->toBe(1);
});
