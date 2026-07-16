<?php

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Artisan::call('permission:sync', ['--no-interaction' => true]);
});

function makeWorkspaceFor(?User $user = null): Workspace
{
    $user ??= User::factory()->create();

    return app(WorkspaceResolver::class)->current($user);
}

function onboardWorkspace(Workspace $workspace): Workspace
{
    $settings = $workspace->settings ?? [];
    $settings['onboarding_completed_at'] = now()->toIso8601String();
    $settings['category'] = 'other';
    $settings['team_size'] = '2-5';

    $workspace->update(['settings' => $settings]);

    return $workspace;
}

function subscribeWorkspace(Workspace $workspace): void
{
    $plan = Plan::query()->firstOrCreate(
        ['slug' => 'default-test-plan'],
        [
            'name' => 'Test Plan',
            'description' => 'A plan for testing',
            'price' => 29,
            'interval' => 'month',
            'limits' => ['team_seats' => 10],
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]
    );

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active->value,
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
        'usage' => [],
    ]);
}

function onboardedWorkspace(User $user): Workspace
{
    $workspace = makeWorkspaceFor($user);
    onboardWorkspace($workspace);
    subscribeWorkspace($workspace);

    return $workspace;
}

it('redirects guests to login', function (): void {
    $this->get(route('user.workspaces.index'))
        ->assertRedirect(route('login'));
});

it('renders the workspace listing for authenticated users', function (): void {
    $user = User::factory()->create();
    onboardedWorkspace($user);

    $this->actingAs($user)
        ->get(route('user.workspaces.index'))
        ->assertSuccessful()
        ->assertViewIs('workspaces::user.index')
        ->assertSee('Workspaces')
        ->assertSee('Create workspace')
        ->assertSee('form-switch__track', false)
        ->assertDontSee('class="switch__track"', false);
});

it('shows owned workspaces in the listing', function (): void {
    $user = User::factory()->create();
    $workspace = onboardedWorkspace($user);

    $this->actingAs($user)
        ->get(route('user.workspaces.index'))
        ->assertSee($workspace->name)
        ->assertSee('Owner');
});

it('shows workspaces where the user is a member', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ownerWorkspace = onboardedWorkspace($owner);

    $ownerWorkspace->members()->attach($member->id, [
        'role' => WorkspaceMemberRole::Administrator->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $member->assignRole('workspace-administrator');

    $memberWorkspace = onboardedWorkspace($member);

    $this->actingAs($member)
        ->get(route('user.workspaces.index'))
        ->assertSeeText($ownerWorkspace->name)
        ->assertSeeText($memberWorkspace->name);
});

it('allows user to create a new workspace', function (): void {
    $user = User::factory()->create();
    onboardedWorkspace($user);

    $this->actingAs($user)
        ->post(route('user.workspaces.store'), [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'timezone' => 'America/New_York',
        ])
        ->assertRedirect(route('user.workspaces.index'));

    $this->assertDatabaseHas('workspaces', [
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_id' => $user->id,
    ]);

    $workspace = Workspace::query()->where('slug', 'acme-corp')->first();
    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();
    expect(session('active_workspace_id'))->toBe($workspace->id);
});

it('validates required fields when creating a workspace', function (): void {
    $user = User::factory()->create();
    onboardedWorkspace($user);

    $this->actingAs($user)
        ->post(route('user.workspaces.store'), [])
        ->assertSessionHasErrors(['name', 'slug']);
});

it('validates unique slug when creating a workspace', function (): void {
    $user = User::factory()->create();
    onboardedWorkspace($user);

    Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => 'Existing',
        'slug' => 'taken-slug',
        'status' => WorkspaceStatus::Active,
        'timezone' => 'UTC',
    ]);

    $this->actingAs($user)
        ->post(route('user.workspaces.store'), [
            'name' => 'New Workspace',
            'slug' => 'taken-slug',
        ])
        ->assertSessionHasErrors(['slug']);
});

it('prevents slug with invalid characters', function (): void {
    $user = User::factory()->create();
    onboardedWorkspace($user);

    $this->actingAs($user)
        ->post(route('user.workspaces.store'), [
            'name' => 'Test',
            'slug' => 'slug with spaces!',
        ])
        ->assertSessionHasErrors(['slug']);
});

it('allows owner to edit their workspace', function (): void {
    $user = User::factory()->create();
    $workspace = onboardedWorkspace($user);

    $this->actingAs($user)
        ->put(route('user.workspaces.update', $workspace), [
            'name' => 'Updated Workspace Name',
            'timezone' => 'Europe/London',
        ])
        ->assertRedirect(route('user.workspaces.index'));

    $this->assertDatabaseHas('workspaces', [
        'id' => $workspace->id,
        'name' => 'Updated Workspace Name',
    ]);
});

it('prevents non-owner from editing a workspace', function (): void {
    $owner = User::factory()->create();
    $workspace = onboardedWorkspace($owner);

    $otherUser = User::factory()->create();
    onboardedWorkspace($otherUser);

    $this->actingAs($otherUser)
        ->put(route('user.workspaces.update', $workspace), [
            'name' => 'Hacked Name',
        ])
        ->assertForbidden();
});

it('allows owner to toggle workspace status', function (): void {
    $user = User::factory()->create();
    $workspace = onboardedWorkspace($user);

    $secondWorkspace = Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => 'Second Workspace',
        'slug' => 'second-ws',
        'status' => WorkspaceStatus::Active,
        'timezone' => 'UTC',
    ]);

    $this->actingAs($user)
        ->patch(route('user.workspaces.toggle-status', $workspace))
        ->assertRedirect(route('user.workspaces.index'));

    expect($workspace->fresh()->status)->toBe(WorkspaceStatus::Suspended);

    $this->actingAs($user)
        ->patch(route('user.workspaces.toggle-status', $workspace))
        ->assertRedirect(route('user.workspaces.index'));

    expect($workspace->fresh()->status)->toBe(WorkspaceStatus::Active);
    expect($secondWorkspace->fresh()->status)->toBe(WorkspaceStatus::Suspended);
    expect(session('active_workspace_id'))->toBe($workspace->id);
});

it('prevents non-owner from toggling workspace status', function (): void {
    $owner = User::factory()->create();
    $workspace = onboardedWorkspace($owner);

    $otherUser = User::factory()->create();
    onboardedWorkspace($otherUser);

    $this->actingAs($otherUser)
        ->patch(route('user.workspaces.toggle-status', $workspace))
        ->assertForbidden();
});

it('allows user to switch to another workspace they own', function (): void {
    $user = User::factory()->create();
    $firstWorkspace = onboardedWorkspace($user);

    $secondWorkspace = Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => 'Second Workspace',
        'slug' => 'second-workspace',
        'status' => WorkspaceStatus::Active,
        'timezone' => 'UTC',
    ]);

    $secondWorkspace->members()->attach($user->id, [
        'role' => WorkspaceMemberRole::Administrator->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $this->actingAs($user)
        ->post(route('user.workspaces.switch', $secondWorkspace))
        ->assertRedirect(route('user.dashboard'));

    expect(session('active_workspace_id'))->toBe($secondWorkspace->id);
});

it('prevents switching to a suspended workspace', function (): void {
    $user = User::factory()->create();
    onboardedWorkspace($user);

    $suspendedWorkspace = Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => 'Suspended Workspace',
        'slug' => 'suspended-ws',
        'status' => WorkspaceStatus::Suspended,
        'timezone' => 'UTC',
    ]);

    $this->actingAs($user)
        ->post(route('user.workspaces.switch', $suspendedWorkspace))
        ->assertStatus(422);
});

it('allows user to accept a pending invitation', function (): void {
    $owner = User::factory()->create();
    $ownerWorkspace = onboardedWorkspace($owner);

    $invitee = User::factory()->create();
    onboardedWorkspace($invitee);

    $invitation = WorkspaceInvitation::query()->create([
        'workspace_id' => $ownerWorkspace->id,
        'email' => $invitee->email,
        'role' => WorkspaceMemberRole::Staff->value,
        'token' => Str::random(64),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->post(route('user.workspaces.invitations.accept', $invitation))
        ->assertRedirect(route('user.workspaces.index'));

    expect($ownerWorkspace->members()->where('users.id', $invitee->id)->exists())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('allows user to decline a pending invitation', function (): void {
    $owner = User::factory()->create();
    $ownerWorkspace = onboardedWorkspace($owner);

    $invitee = User::factory()->create();
    onboardedWorkspace($invitee);

    $invitation = WorkspaceInvitation::query()->create([
        'workspace_id' => $ownerWorkspace->id,
        'email' => $invitee->email,
        'role' => WorkspaceMemberRole::Staff->value,
        'token' => Str::random(64),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->post(route('user.workspaces.invitations.decline', $invitation))
        ->assertRedirect(route('user.workspaces.index'));

    $this->assertModelMissing($invitation);
});

it('allows user to leave a workspace they belong to', function (): void {
    $owner = User::factory()->create();
    $workspace = onboardedWorkspace($owner);

    $member = User::factory()->create();
    onboardedWorkspace($member);

    $workspace->members()->attach($member->id, [
        'role' => WorkspaceMemberRole::Administrator->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $member->assignRole('workspace-administrator');

    $this->actingAs($member)
        ->post(route('user.workspaces.leave', $workspace))
        ->assertRedirect(route('user.workspaces.index'));

    expect($workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

it('prevents owner from leaving their own workspace', function (): void {
    $owner = User::factory()->create();
    $workspace = onboardedWorkspace($owner);

    $this->actingAs($owner)
        ->post(route('user.workspaces.leave', $workspace))
        ->assertStatus(422);
});

it('shows pending invitations on the workspace page', function (): void {
    $owner = User::factory()->create();
    $ownerWorkspace = onboardedWorkspace($owner);

    $invitee = User::factory()->create();
    onboardedWorkspace($invitee);

    WorkspaceInvitation::query()->create([
        'workspace_id' => $ownerWorkspace->id,
        'email' => $invitee->email,
        'role' => WorkspaceMemberRole::Staff->value,
        'token' => Str::random(64),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->get(route('user.workspaces.index'))
        ->assertSuccessful()
        ->assertSee('Pending invitations')
        ->assertSee($ownerWorkspace->name)
        ->assertSee('Accept')
        ->assertSee('Decline');
});

it('does not show other users invitations', function (): void {
    $owner = User::factory()->create();
    $ownerWorkspace = onboardedWorkspace($owner);

    $intendedInvitee = User::factory()->create();
    onboardedWorkspace($intendedInvitee);

    $otherUser = User::factory()->create();
    onboardedWorkspace($otherUser);

    WorkspaceInvitation::query()->create([
        'workspace_id' => $ownerWorkspace->id,
        'email' => $intendedInvitee->email,
        'role' => WorkspaceMemberRole::Staff->value,
        'token' => Str::random(64),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($otherUser)
        ->get(route('user.workspaces.index'))
        ->assertSuccessful()
        ->assertDontSee('Pending invitations');
});

it('shows active/suspended status badges on workspace cards', function (): void {
    $owner = User::factory()->create();
    $activeWs = onboardedWorkspace($owner);

    $suspendedWs = Workspace::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Suspended Co.',
        'slug' => 'suspended-co',
        'status' => WorkspaceStatus::Suspended,
        'timezone' => 'UTC',
    ]);

    $suspendedWs->members()->attach($owner->id, [
        'role' => WorkspaceMemberRole::Administrator->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $this->actingAs($owner)
        ->get(route('user.workspaces.index'))
        ->assertSuccessful()
        ->assertSee('Active')
        ->assertSee('Suspended');
});

it('shows the correct role badge for member workspaces', function (): void {
    $owner = User::factory()->create();
    $workspace = onboardedWorkspace($owner);

    $admin = User::factory()->create();
    onboardedWorkspace($admin);
    $workspace->members()->attach($admin->id, [
        'role' => WorkspaceMemberRole::Administrator->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $admin->assignRole('workspace-administrator');

    $this->actingAs($admin)
        ->get(route('user.workspaces.index'))
        ->assertSuccessful()
        ->assertSee('Admin');
});

it('allows owner to delete a workspace with no services', function (): void {
    $owner = User::factory()->create();
    onboardedWorkspace($owner);

    $emptyWorkspace = Workspace::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Empty Workspace',
        'slug' => 'empty-workspace',
        'status' => WorkspaceStatus::Active,
        'timezone' => 'UTC',
    ]);

    $this->actingAs($owner)
        ->delete(route('user.workspaces.destroy', $emptyWorkspace))
        ->assertRedirect(route('user.workspaces.index'));

    $this->assertModelMissing($emptyWorkspace);
});

it('prevents deleting a workspace that has services', function (): void {
    $owner = User::factory()->create();
    $workspaceWithServices = onboardedWorkspace($owner);

    $this->actingAs($owner)
        ->delete(route('user.workspaces.destroy', $workspaceWithServices))
        ->assertForbidden();
});

it('prevents non-owner from deleting a workspace', function (): void {
    $owner = User::factory()->create();
    onboardedWorkspace($owner);

    $emptyWorkspace = Workspace::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Cant Delete',
        'slug' => 'cant-delete',
        'status' => WorkspaceStatus::Active,
        'timezone' => 'UTC',
    ]);

    $otherUser = User::factory()->create();
    onboardedWorkspace($otherUser);

    $this->actingAs($otherUser)
        ->delete(route('user.workspaces.destroy', $emptyWorkspace))
        ->assertForbidden();
});

it('shows delete button only for workspaces without services', function (): void {
    $owner = User::factory()->create();
    $activeWs = onboardedWorkspace($owner);

    Workspace::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Deletable WS',
        'slug' => 'deletable-ws',
        'status' => WorkspaceStatus::Active,
        'timezone' => 'UTC',
    ]);

    $this->actingAs($owner)
        ->get(route('user.workspaces.index'))
        ->assertSuccessful()
        ->assertSee('Delete workspace');
});

it('deactivates other owned workspaces when a new workspace is created', function (): void {
    $user = User::factory()->create();
    $firstWorkspace = onboardedWorkspace($user);

    $this->actingAs($user)
        ->post(route('user.workspaces.store'), [
            'name' => 'Second Workspace',
            'slug' => 'second-ws',
            'timezone' => 'UTC',
        ])
        ->assertRedirect(route('user.workspaces.index'));

    expect($firstWorkspace->fresh()->status)->toBe(WorkspaceStatus::Suspended);

    $secondWorkspace = Workspace::query()->where('slug', 'second-ws')->first();
    expect($secondWorkspace->status)->toBe(WorkspaceStatus::Active);
});

it('deactivates other owned workspaces when a workspace is toggled to active', function (): void {
    $user = User::factory()->create();
    $firstWorkspace = onboardedWorkspace($user);

    $secondWorkspace = Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => 'Second Workspace',
        'slug' => 'second-ws',
        'status' => WorkspaceStatus::Suspended,
        'timezone' => 'UTC',
    ]);

    $this->actingAs($user)
        ->patch(route('user.workspaces.toggle-status', $secondWorkspace))
        ->assertRedirect(route('user.workspaces.index'));

    expect($firstWorkspace->fresh()->status)->toBe(WorkspaceStatus::Suspended);
    expect($secondWorkspace->fresh()->status)->toBe(WorkspaceStatus::Active);
});

it('prevents deactivating the only active workspace', function (): void {
    $user = User::factory()->create();
    $workspace = onboardedWorkspace($user);

    $this->actingAs($user)
        ->patch(route('user.workspaces.toggle-status', $workspace))
        ->assertRedirect(route('user.workspaces.index'))
        ->assertSessionHas('error', 'You must have at least one active workspace.');

    expect($workspace->fresh()->status)->toBe(WorkspaceStatus::Active);
});
