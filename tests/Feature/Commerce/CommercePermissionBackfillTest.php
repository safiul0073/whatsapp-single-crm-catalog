<?php

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\WorkspaceRole;
use App\Modules\Workspaces\Models\WorkspaceRolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function runCommerceBackfill(): void
{
    (require database_path('migrations/2026_10_24_000001_backfill_granular_commerce_permissions.php'))->up();
}

it('converts legacy commerce workspace role permissions into granular ones', function () {
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create());
    $viewer = WorkspaceRole::query()->create(['workspace_id' => $workspace->id, 'name' => 'Viewer', 'is_system' => false]);
    $manager = WorkspaceRole::query()->create(['workspace_id' => $workspace->id, 'name' => 'Seller', 'is_system' => false]);
    foreach ([[$viewer, 'commerce.view'], [$manager, 'commerce.view'], [$manager, 'commerce.manage'], [$manager, 'inbox.view']] as [$role, $permission]) {
        WorkspaceRolePermission::query()->create(['workspace_id' => $workspace->id, 'workspace_role_id' => $role->id, 'permission_name' => $permission]);
    }

    runCommerceBackfill();

    $viewerPermissions = WorkspaceRolePermission::query()->where('workspace_role_id', $viewer->id)->pluck('permission_name');
    $managerPermissions = WorkspaceRolePermission::query()->where('workspace_role_id', $manager->id)->pluck('permission_name');

    expect($viewerPermissions)->toContain('commerce.orders.view', 'commerce.orders.payments.view')
        ->not->toContain('commerce.view', 'commerce.orders.create', 'commerce.storefront.settings')
        ->and($managerPermissions)->toContain('commerce.orders.view', 'commerce.orders.create', 'commerce.storefront.domains', 'inbox.view')
        ->not->toContain('commerce.view', 'commerce.manage');
});

it('converts legacy spatie commerce grants', function () {
    $role = Role::findOrCreate('Legacy seller', 'web');
    $role->givePermissionTo(Permission::findOrCreate('commerce.manage', 'web'));

    runCommerceBackfill();

    expect($role->fresh()->permissions->pluck('name'))->toContain('commerce.pos.use', 'commerce.products.manage')
        ->not->toContain('commerce.manage')
        ->and(Permission::query()->where('name', 'commerce.manage')->exists())->toBeFalse();
});
