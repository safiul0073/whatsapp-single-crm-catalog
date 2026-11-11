<?php

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Services\PlanFeatureGate;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceRole;
use App\Modules\Workspaces\Models\WorkspaceRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @return array{0: Workspace, 1: User}
 */
function planPermissionsSetup(User $owner): array
{
    Role::findOrCreate('workspace-owner', 'web');
    $owner->assignRole('workspace-owner');

    $workspace = app(WorkspaceResolver::class)->current($owner);
    $settings = $workspace->settings ?? [];
    $settings['category'] = 'other';
    $settings['team_size'] = '2-5';
    $settings['onboarding_completed_at'] = now()->toIso8601String();
    $workspace->update(['timezone' => 'UTC', 'settings' => $settings]);

    $role = WorkspaceRole::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Support Agent',
        'description' => 'Support',
        'is_system' => false,
    ]);

    foreach (['workspace.view', 'contacts.view'] as $permission) {
        WorkspaceRolePermission::query()->create([
            'workspace_id' => $workspace->id,
            'workspace_role_id' => $role->id,
            'permission_name' => $permission,
        ]);
    }

    $member = User::factory()->create();
    $workspace->members()->attach($member->id, [
        'workspace_role_id' => $role->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return [$workspace, $member];
}

it('hides permissions that are not in the plan and keeps their grants on save', function (): void {
    $owner = User::factory()->create();
    [$workspace, $member] = planPermissionsSetup($owner);

    $gate = Mockery::mock(app(PlanFeatureGate::class));
    $gate->shouldReceive('includesPermission')
        ->andReturnUsing(fn (int $workspaceId, string $permission): bool => $permission !== 'contacts.view');
    app()->instance(PlanFeatureGate::class, $gate);

    $html = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.workspaces.team.permissions', $member))
        ->assertOk()
        ->assertSee('workspace.view')
        ->assertDontSee('Not in your plan')
        ->getContent();

    expect($html)
        ->toContain('<input type="hidden" name="permissions[]" value="contacts.view" />')
        ->not->toMatch('/type="checkbox"\s+name="permissions\[\]"\s+value="contacts\.view"/');

    $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->put(route('user.workspaces.team.permissions.update', $member), [
            'permissions' => ['workspace.view', 'contacts.view'],
        ])
        ->assertRedirect(route('user.workspaces.team.permissions', $member));

    expect(WorkspaceRolePermission::query()
        ->where('workspace_id', $workspace->id)
        ->pluck('permission_name')
        ->all())->toContain('contacts.view');
});
