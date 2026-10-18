<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceRole;
use App\Modules\Workspaces\Models\WorkspaceRolePermission;
use App\Services\Navigation\WorkspaceNavigationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Artisan::call('permission:sync', ['--no-interaction' => true]);
});

function createNavigationWorkspace(User $owner): Workspace
{
    Role::findOrCreate('workspace-owner', 'web');
    $owner->assignRole('workspace-owner');

    $workspace = app(WorkspaceResolver::class)->current($owner);
    $settings = $workspace->settings ?? [];
    $settings['category'] = 'other';
    $settings['team_size'] = '2-5';
    $settings['onboarding_completed_at'] = now()->toIso8601String();

    $workspace->update([
        'name' => $owner->name."'s Workspace",
        'timezone' => 'UTC',
        'settings' => $settings,
    ]);

    return $workspace;
}

it('resolves all seven top-level modules for a workspace owner', function (): void {
    $owner = User::factory()->create();
    createNavigationWorkspace($owner);

    $this->actingAs($owner);

    $navigation = app(WorkspaceNavigationRegistry::class)->forUser($owner);
    $moduleIds = array_column($navigation['modules'], 'id');

    expect($moduleIds)->toBe([
        'dashboard',
        'inbox',
        'crm',
        'marketing',
        'automation',
        'commerce',
        'channels',
    ]);
});

it('activates the correct top-level module and contextual sidebar panel on deep links', function (): void {
    $owner = User::factory()->create();
    createNavigationWorkspace($owner);

    // 1. Deep link inside Commerce (Storefront settings)
    $storefrontResponse = $this->actingAs($owner)
        ->get(route('user.commerce.storefront.index'))
        ->assertOk();

    $storefrontHtml = $storefrontResponse->getContent();

    expect($storefrontHtml)
        ->toMatch('/data-module-trigger="commerce"[\s\S]*?class="[^"]*app-topnav__link[^"]*is-active[^"]*"/')
        ->toMatch('/class="app-nav__module-panel\s*"\s*data-module-panel="commerce"/')
        ->toMatch('/class="app-nav__module-panel\s+is-hidden"\s*data-module-panel="crm"/')
        ->toMatch('/href="'.preg_quote(route('user.commerce.storefront.index'), '/').'"[^>]*class="[^"]*app-nav__link[^"]*is-active[^"]*"/');

    // 2. Deep link inside CRM (Tags)
    $tagsResponse = $this->actingAs($owner)
        ->get(route('user.tags.index'))
        ->assertOk();

    $tagsHtml = $tagsResponse->getContent();

    expect($tagsHtml)
        ->toMatch('/data-module-trigger="crm"[\s\S]*?class="[^"]*app-topnav__link[^"]*is-active[^"]*"/')
        ->toMatch('/class="app-nav__module-panel\s*"\s*data-module-panel="crm"/')
        ->toMatch('/class="app-nav__module-panel\s+is-hidden"\s*data-module-panel="commerce"/')
        ->toMatch('/href="'.preg_quote(route('user.tags.index'), '/').'"[^>]*class="[^"]*app-nav__link[^"]*is-active[^"]*"/');
});

it('filters top-level modules and contextual sidebar links by workspace role permissions', function (): void {
    $owner = User::factory()->create();
    $workspace = createNavigationWorkspace($owner);

    $staffRole = WorkspaceRole::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Support Agent',
        'description' => 'Handles inbox and views orders',
        'is_system' => false,
    ]);

    foreach (['workspace.view', 'inbox.assigned_only', 'contacts.assigned_only', 'commerce.view'] as $perm) {
        WorkspaceRolePermission::query()->create([
            'workspace_id' => $workspace->id,
            'workspace_role_id' => $staffRole->id,
            'permission_name' => $perm,
        ]);
    }

    $staff = User::factory()->create();

    $workspace->members()->attach($staff->id, [
        'workspace_role_id' => $staffRole->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($staff)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get(route('user.dashboard'))
        ->assertOk();

    $navigation = app(WorkspaceNavigationRegistry::class)->forUser($staff);
    $moduleIds = array_column($navigation['modules'], 'id');

    // Staff has workspace.view, inbox.assigned_only, contacts.assigned_only, commerce.view
    // Staff does NOT have campaigns.view, templates.manage, automations.manage, chatbots.manage
    expect($moduleIds)
        ->toContain('dashboard', 'inbox', 'crm', 'commerce')
        ->not->toContain('marketing', 'automation');

    $html = $response->getContent();
    expect($html)
        ->toContain('data-nav-route="user.inbox.index"')
        ->toContain('data-nav-route="user.commerce.orders.index"')
        ->not->toContain('data-nav-route="user.commerce.storefront.index"')
        ->not->toContain('data-nav-route="user.workspaces.team"')
        ->not->toContain('data-nav-route="user.message-templates.index"');
});

it('indexes permitted navigation destinations in global search', function (): void {
    $owner = User::factory()->create();
    createNavigationWorkspace($owner);

    $this->actingAs($owner)
        ->getJson(route('user.global-search', ['q' => 'storefront']))
        ->assertOk()
        ->assertJsonFragment([
            'title' => __('Storefront'),
            'url' => route('user.commerce.storefront.index'),
        ]);

    $this->actingAs($owner)
        ->getJson(route('user.global-search', ['q' => 'tags']))
        ->assertOk()
        ->assertJsonFragment([
            'title' => __('Contact Tags'),
            'url' => route('user.tags.index'),
        ]);
});
