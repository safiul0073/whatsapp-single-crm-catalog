<?php

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Services\CRMLeadService;
use App\Modules\Crm\Services\LeadAssignmentService;
use App\Modules\Crm\Services\TaskService;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    $this->workspace->members()->detach($this->owner->id);
    $this->contact = Contact::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Customer', 'phone' => '+15555550123']);
    $this->conversation = Conversation::query()->create(['workspace_id' => $this->workspace->id, 'provider' => 'whatsapp', 'contact_id' => $this->contact->id, 'status' => 'open']);
    $this->lead = app(CRMLeadService::class)->createOrUpdate($this->workspace->id, $this->contact->id, ['conversation_id' => $this->conversation->id]);
    Permission::findOrCreate('crm.manage', 'web');
    $this->owner->givePermissionTo('crm.manage');
    $this->withoutMiddleware()->actingAs($this->owner);
});

it('exposes the owner id and assigns the owner without a membership row', function (): void {
    $this->getJson(route('user.inbox.conversations.crm', $this->conversation))
        ->assertSuccessful()->assertJsonPath('crm.owner_id', $this->owner->id);
    $this->patchJson(route('user.crm.leads.assign', $this->lead), ['assigned_to' => $this->owner->id])->assertSuccessful();
    expect($this->lead->fresh()->assigned_to)->toBe($this->owner->id)
        ->and($this->contact->fresh()->assigned_to)->toBe($this->owner->id)
        ->and($this->conversation->fresh()->assigned_to)->toBe($this->owner->id)
        ->and(CrmActivity::query()->where('lead_id', $this->lead->id)->where('type', 'assigned')->sole()->metadata['assigned_to'])->toBe($this->owner->id);
});

it('accepts active members and rejects inactive members and outsiders', function (string $status, bool $allowed): void {
    $agent = User::factory()->create();
    if ($status !== 'outsider') {
        $workspace = $status === 'other-workspace' ? app(WorkspaceResolver::class)->current($agent) : $this->workspace;
        $role = WorkspaceRole::query()->firstOrCreate(['workspace_id' => $workspace->id, 'name' => 'Staff']);
        $workspace->members()->syncWithoutDetaching([$agent->id => ['workspace_role_id' => $role->id, 'status' => $status === 'other-workspace' ? 'active' : $status]]);
    }
    $response = $this->patchJson(route('user.crm.leads.assign', $this->lead), ['assigned_to' => $agent->id]);
    if ($allowed) {
        $response->assertSuccessful();
        expect($this->lead->fresh()->assigned_to)->toBe($agent->id);
    } else {
        $response->assertNotFound();
        expect($this->lead->fresh()->assigned_to)->toBeNull()->and($this->contact->fresh()->assigned_to)->toBeNull()->and($this->conversation->fresh()->assigned_to)->toBeNull();
    }
})->with([['active', true], ['suspended', false], ['invited', false], ['outsider', false], ['other-workspace', false]]);

it('keeps assignment required and permissions enforced', function (): void {
    $this->patchJson(route('user.crm.leads.assign', $this->lead), ['assigned_to' => ''])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
    $member = User::factory()->create();
    $role = WorkspaceRole::query()->firstOrCreate(['workspace_id' => $this->workspace->id, 'name' => 'Staff']);
    $this->workspace->members()->attach($member->id, ['workspace_role_id' => $role->id, 'status' => 'active']);
    $this->actingAs($member);
    $this->patchJson(route('user.crm.leads.assign', $this->lead), ['assigned_to' => $this->owner->id])->assertForbidden();
});

it('assigns a task explicitly to the owner instead of inheriting the lead assignee', function (): void {
    $agent = User::factory()->create();
    $role = WorkspaceRole::query()->firstOrCreate(['workspace_id' => $this->workspace->id, 'name' => 'Staff']);
    $this->workspace->members()->attach($agent->id, ['workspace_role_id' => $role->id, 'status' => 'active']);
    app(LeadAssignmentService::class)->assign($this->workspace->id, $this->lead->id, $agent->id);
    $task = app(TaskService::class)->create($this->workspace->id, ['lead_id' => $this->lead->id, 'assigned_to' => $this->owner->id, 'title' => 'Follow up', 'due_at' => now()->addDay()], $agent);
    expect($task->assigned_to)->toBe($this->owner->id)->and($this->lead->fresh()->assigned_to)->toBe($agent->id);
});
