<?php

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Mail\TeamInvitationMail;
use App\Modules\Workspaces\Mail\TeamMemberWelcomeMail;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Artisan::call('permission:sync', ['--no-interaction' => true]);
    Mail::fake();
    Notification::fake();
});

function workspaceFor(User $user): Workspace
{
    return app(WorkspaceResolver::class)->current($user);
}

function setupWorkspace(User $user, int $seats = 10): Workspace
{
    $workspace = workspaceFor($user);
    completeOnboarding($workspace);
    activatePlan($workspace, createPlanWithSeats($seats));

    return $workspace;
}

function createPlanWithSeats(int $seats = 10): Plan
{
    return Plan::query()->create([
        'name' => 'Test Plan',
        'slug' => 'test-plan-'.$seats,
        'description' => 'Test plan',
        'price' => 29,
        'interval' => 'month',
        'limits' => ['team_seats' => $seats],
        'features' => [],
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function activatePlan(Workspace $workspace, Plan $plan): void
{
    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active->value,
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
        'ends_at' => null,
        'usage' => [],
    ]);
}

function completeOnboarding(Workspace $workspace): void
{
    $settings = $workspace->settings ?? [];
    $settings['category'] = 'other';
    $settings['team_size'] = '2-5';
    $settings['onboarding_completed_at'] = now()->toIso8601String();

    $workspace->update([
        'name' => 'Test Workspace',
        'timezone' => 'UTC',
        'settings' => $settings,
    ]);
}

function addMember(Workspace $workspace, WorkspaceMemberRole $role): User
{
    $user = User::factory()->create();

    $workspace->members()->attach($user->id, [
        'role' => $role->value,
        'status' => 'active',
    ]);

    $spatieRole = match ($role) {
        WorkspaceMemberRole::Administrator => 'workspace-administrator',
        WorkspaceMemberRole::Manager => 'workspace-manager',
        WorkspaceMemberRole::Staff => 'workspace-staff',
    };

    $user->assignRole($spatieRole);

    return $user;
}

it('allows workspace owner to view the team page', function (): void {
    $owner = User::factory()->create();
    setupWorkspace($owner);

    $response = $this->actingAs($owner)
        ->get(route('user.workspaces.team'))
        ->assertSuccessful()
        ->assertViewIs('workspaces::user.team')
        ->assertDontSee('Role permissions')
        ->assertSee($owner->name);

    $content = $response->getContent();

    expect(preg_match('/href="'.preg_quote(route('user.workspaces.team'), '/').'"[^>]*class="[^"]*app-nav__link[^"]*is-active[^"]*"/', $content))->toBe(1);
    expect(preg_match('/href="'.preg_quote(route('user.workspaces.index'), '/').'"[^>]*class="[^"]*app-nav__link[^"]*is-active[^"]*"/', $content))->toBe(0);
});

it('prevents non-admin team members from viewing the team page', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $staff = addMember($workspace, WorkspaceMemberRole::Staff);

    $this->actingAs($staff)
        ->get(route('user.workspaces.team'))
        ->assertForbidden();
});

it('allows owner to directly add a team member', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $this->actingAs($owner)
        ->post(route('user.workspaces.team.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => WorkspaceMemberRole::Manager->value,
        ])
        ->assertRedirect(route('user.workspaces.team'));

    $this->assertDatabaseHas('users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    $user = User::query()->where('email', 'jane@example.com')->first();
    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();
    expect($user->hasRole('workspace-manager'))->toBeTrue();

    Mail::assertQueued(TeamMemberWelcomeMail::class);
});

it('enforces the team seat limit when adding members', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner, 1);

    addMember($workspace, WorkspaceMemberRole::Staff);

    $this->actingAs($owner)
        ->post(route('user.workspaces.team.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => WorkspaceMemberRole::Staff->value,
        ])
        ->assertForbidden();
});

it('allows manager to add staff but not administrators', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $manager = addMember($workspace, WorkspaceMemberRole::Manager);

    $this->actingAs($manager)
        ->post(route('user.workspaces.team.store'), [
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => WorkspaceMemberRole::Staff->value,
        ])
        ->assertRedirect(route('user.workspaces.team'));

    $this->actingAs($manager)
        ->post(route('user.workspaces.team.store'), [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => WorkspaceMemberRole::Administrator->value,
        ])
        ->assertForbidden();
});

it('allows owner to invite a team member by email', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $this->actingAs($owner)
        ->post(route('user.workspaces.team.invite'), [
            'email' => 'invited@example.com',
            'role' => WorkspaceMemberRole::Staff->value,
        ])
        ->assertRedirect(route('user.workspaces.team'));

    $this->assertDatabaseHas('workspace_invitations', [
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'role' => WorkspaceMemberRole::Staff->value,
    ]);

    Mail::assertQueued(TeamInvitationMail::class);
});

it('allows owner to edit a team member', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $member = addMember($workspace, WorkspaceMemberRole::Staff);

    $this->actingAs($owner)
        ->put(route('user.workspaces.team.update', $member), [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => $member->email,
            'role' => WorkspaceMemberRole::Manager->value,
        ])
        ->assertRedirect(route('user.workspaces.team'));

    expect($member->fresh()->first_name)->toBe('Updated');
    expect($member->fresh()->hasRole('workspace-manager'))->toBeTrue();
});

it('allows owner to open member role permissions from the team table', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $member = addMember($workspace, WorkspaceMemberRole::Staff);

    $this->actingAs($owner)
        ->get(route('user.workspaces.team'))
        ->assertOk()
        ->assertSee(route('user.workspaces.team.permissions', $member), false);

    $this->actingAs($owner)
        ->get(route('user.workspaces.team.permissions', $member))
        ->assertOk()
        ->assertViewIs('workspaces::user.permissions')
        ->assertSee('Role permissions')
        ->assertSee($member->name)
        ->assertSee('workspace.view');
});

it('allows owner to update role permissions from a member permission page', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);
    $member = addMember($workspace, WorkspaceMemberRole::Staff);

    $this->actingAs($owner)
        ->put(route('user.workspaces.team.permissions.update', $member), [
            'permissions' => [
                'workspace.view',
                'contacts.view',
            ],
        ])
        ->assertRedirect(route('user.workspaces.team.permissions', $member));

    $role = Role::findByName('workspace-staff', 'web');

    expect($role->hasPermissionTo('workspace.view'))->toBeTrue()
        ->and($role->hasPermissionTo('contacts.view'))->toBeTrue()
        ->and($role->hasPermissionTo('reports.view'))->toBeFalse();
});

it('hides sidebar items for permissions missing from the active workspace role', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $staff = User::factory()->create();
    setupWorkspace($staff, 11);

    $workspace->members()->attach($staff->id, [
        'role' => WorkspaceMemberRole::Staff->value,
        'status' => 'active',
    ]);

    $staff->assignRole('workspace-staff');

    $this->actingAs($staff)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.dashboard'))
        ->assertOk()
        ->assertSee('Inbox')
        ->assertDontSee('Groups')
        ->assertDontSee('Templates');
});

it('uses the active workspace role instead of an owned workspace role', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $staff = User::factory()->create();
    setupWorkspace($staff, 11);

    $workspace->members()->attach($staff->id, [
        'role' => WorkspaceMemberRole::Staff->value,
        'status' => 'active',
    ]);

    $staff->assignRole('workspace-owner');

    $this->actingAs($staff)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.workspaces.team'))
        ->assertForbidden();
});

it('allows an active workspace manager to manage staff only even when they own another workspace', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $manager = User::factory()->create();
    setupWorkspace($manager, 11);

    $workspace->members()->attach($manager->id, [
        'role' => WorkspaceMemberRole::Manager->value,
        'status' => 'active',
    ]);

    $manager->assignRole('workspace-owner');

    $this->actingAs($manager)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->post(route('user.workspaces.team.store'), [
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => 'workspace-staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => WorkspaceMemberRole::Staff->value,
        ])
        ->assertRedirect(route('user.workspaces.team'));

    $this->actingAs($manager)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->post(route('user.workspaces.team.store'), [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'workspace-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => WorkspaceMemberRole::Administrator->value,
        ])
        ->assertForbidden();
});

it('keeps sidebar and route middleware aligned with the active workspace role', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $staff = User::factory()->create();
    setupWorkspace($staff, 11);

    $workspace->members()->attach($staff->id, [
        'role' => WorkspaceMemberRole::Staff->value,
        'status' => 'active',
    ]);

    $staff->assignRole('workspace-owner');

    $this->actingAs($staff)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.dashboard'))
        ->assertOk()
        ->assertDontSee(route('user.workspaces.team'), false);

    $this->actingAs($staff)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.workspaces.team'))
        ->assertForbidden();
});

it('applies edited role permissions to active workspace access checks', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);
    $staff = addMember($workspace, WorkspaceMemberRole::Staff);

    $this->actingAs($staff)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.message-templates.index'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->put(route('user.workspaces.team.permissions.update', $staff), [
            'permissions' => [
                'workspace.view',
                'templates.manage',
            ],
        ])
        ->assertRedirect(route('user.workspaces.team.permissions', $staff));

    $this->actingAs($staff)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.message-templates.index'))
        ->assertOk();
});

it('prevents editing or removing the workspace owner', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $admin = addMember($workspace, WorkspaceMemberRole::Administrator);

    $this->actingAs($admin)
        ->put(route('user.workspaces.team.update', $owner), [
            'first_name' => 'Hacked',
            'last_name' => 'Owner',
            'email' => $owner->email,
            'role' => WorkspaceMemberRole::Staff->value,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('user.workspaces.team.destroy', $owner))
        ->assertForbidden();
});

it('allows owner to remove a team member', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $member = addMember($workspace, WorkspaceMemberRole::Staff);

    $this->actingAs($owner)
        ->delete(route('user.workspaces.team.destroy', $member))
        ->assertRedirect(route('user.workspaces.team'));

    expect($workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
    expect($member->fresh()->hasRole('workspace-staff'))->toBeFalse();
});

it('allows owner to resend and revoke invitations', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $invitation = WorkspaceInvitation::query()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'role' => WorkspaceMemberRole::Staff->value,
        'token' => 'test-token-123',
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($owner)
        ->post(route('user.workspaces.team.invitations.resend', $invitation))
        ->assertRedirect(route('user.workspaces.team'));

    Mail::assertQueued(TeamInvitationMail::class);

    $this->actingAs($owner)
        ->delete(route('user.workspaces.team.invitations.revoke', $invitation))
        ->assertRedirect(route('user.workspaces.team'));

    $this->assertModelMissing($invitation);
});

it('allows invitee to accept invitation and create account', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $invitation = WorkspaceInvitation::query()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'role' => WorkspaceMemberRole::Staff->value,
        'token' => 'test-token-123',
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->post(route('invite.accept', $invitation->token), [
        'name' => 'Invited User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertRedirect(route('user.dashboard'));

    $this->assertDatabaseHas('users', [
        'email' => 'invited@example.com',
        'first_name' => 'Invited',
        'last_name' => 'User',
    ]);

    $user = User::query()->where('email', 'invited@example.com')->first();
    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();
    expect($user->hasRole('workspace-staff'))->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('rejects expired or already accepted invitations', function (): void {
    $owner = User::factory()->create();
    $workspace = setupWorkspace($owner);

    $invitation = WorkspaceInvitation::query()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invited@example.com',
        'role' => WorkspaceMemberRole::Staff->value,
        'token' => 'expired-token',
        'invited_by' => $owner->id,
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('invite.show', $invitation->token))
        ->assertNotFound();
});
