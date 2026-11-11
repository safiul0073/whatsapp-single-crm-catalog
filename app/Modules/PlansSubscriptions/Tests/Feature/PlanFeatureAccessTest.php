<?php

use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\Admin;
use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Services\PlanFeatureGate;
use App\Modules\PlansSubscriptions\Services\SubscriptionManager;
use App\Modules\Workspaces\Models\Workspace;
use App\Services\Navigation\WorkspaceNavigationRegistry;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\WithoutDefaultTestPlan;

uses(RefreshDatabase::class, WithoutDefaultTestPlan::class);

/**
 * @param  list<string>  $blocked
 * @return array{0: User, 1: Workspace, 2: Plan}
 */
function workspaceOnPlanBlocking(array $blocked): array
{
    $plan = Plan::query()->create([
        'name' => 'Plan '.Str::random(6),
        'slug' => 'plan-'.Str::lower(Str::random(8)),
        'price' => 0,
        'is_free' => true,
        'interval' => Plan::INTERVAL_MONTH,
        'limits' => [Plan::BLOCKED_FEATURES => $blocked],
        'features' => [],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $workspace->update(['settings' => ['onboarding_completed_at' => now()->toIso8601String()]]);
    app(SubscriptionManager::class)->activate($workspace, $plan);

    return [$user, $workspace->fresh(), $plan];
}

/**
 * @return list<string>
 */
function visibleNavigationRoutes(User $user): array
{
    $request = Request::create('/dashboard');
    $request->setRouteResolver(fn () => null);

    return collect(app(WorkspaceNavigationRegistry::class)->forUser($user, $request)['flat_items'])
        ->map(fn (array $item): string => $item['module_id'].'|'.$item['route'])
        ->all();
}

it('builds a catalog with unique keys pointing at real routes', function (): void {
    $registry = app(WorkspaceNavigationRegistry::class);
    $keys = $registry->featureKeys();

    expect($keys)->toBe(array_values(array_unique($keys)))
        ->and($keys)->toContain('commerce', 'commerce:commerce.pos.index')
        ->and($keys)->not->toContain('dashboard');

    foreach ($registry->featureCatalog() as $module) {
        foreach ($module['items'] as $item) {
            expect(Route::has($item['route']))->toBeTrue("Missing route {$item['route']}");
        }
    }
});

it('hides a blocked module and redirects its URLs to the subscription page', function (): void {
    [$user] = workspaceOnPlanBlocking(['commerce']);

    $routes = visibleNavigationRoutes($user);

    expect(collect($routes)->filter(fn (string $route): bool => str_starts_with($route, 'commerce|')))->toBeEmpty()
        ->and($routes)->toContain('inbox|user.inbox.index');

    $this->actingAs($user)
        ->get(route('user.commerce.pos.index'))
        ->assertRedirect(route('user.subscription.show'))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'POS'));
});

it('blocks a single menu item while the rest of the module stays available', function (): void {
    [$user, $workspace] = workspaceOnPlanBlocking(['commerce:commerce.pos.index']);
    $gate = app(PlanFeatureGate::class);

    $routes = visibleNavigationRoutes($user);

    expect($routes)->not->toContain('commerce|user.commerce.pos.index')
        ->and($routes)->toContain('commerce|user.commerce.orders.index')
        ->and($gate->allowsRoute($workspace->id, 'user.commerce.pos.index'))->toBeFalse()
        ->and($gate->allowsRoute($workspace->id, 'user.commerce.orders.index'))->toBeTrue()
        ->and($gate->allowsRoute($workspace->id, 'user.commerce.products.store'))->toBeTrue();
});

it('blocks sibling routes of a blocked item and shared routes only when every entry is blocked', function (): void {
    [, $workspace] = workspaceOnPlanBlocking(['commerce:commerce.storefront.index', 'meta:commerce.catalog']);
    $gate = app(PlanFeatureGate::class);

    expect($gate->allowsRoute($workspace->id, 'user.commerce.storefront.index'))->toBeFalse()
        ->and($gate->allowsRoute($workspace->id, 'user.commerce.storefront.theme.update'))->toBeFalse()
        ->and($gate->allowsRoute($workspace->id, 'user.commerce.storefront.live'))->toBeTrue()
        ->and($gate->allowsRoute($workspace->id, 'user.commerce.catalog'))->toBeTrue();

    [, $otherWorkspace] = workspaceOnPlanBlocking(['commerce:commerce.catalog', 'meta:commerce.catalog']);

    expect(app(PlanFeatureGate::class)->allowsRoute($otherWorkspace->id, 'user.commerce.catalog'))->toBeFalse();
});

it('returns 403 for JSON requests to a blocked feature', function (): void {
    [$user] = workspaceOnPlanBlocking(['inbox']);

    $this->actingAs($user)
        ->getJson(route('user.inbox.index'))
        ->assertForbidden();
});

it('always allows the dashboard, profile and subscription pages', function (): void {
    [$user, $workspace] = workspaceOnPlanBlocking(['inbox', 'crm', 'marketing', 'meta', 'automation', 'commerce', 'channels']);
    $gate = app(PlanFeatureGate::class);

    foreach (['user.dashboard', 'user.profile.edit', 'user.subscription.show', 'user.workspaces.team', 'user.global-search'] as $route) {
        expect($gate->allowsRoute($workspace->id, $route))->toBeTrue("{$route} should stay reachable");
    }

    $this->actingAs($user)->get(route('user.subscription.show'))->assertOk();

    expect(collect(visibleNavigationRoutes($user))->every(fn (string $route): bool => str_starts_with($route, 'dashboard|')))->toBeTrue();
});

it('leaves workspaces without a subscription unrestricted', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);

    expect(app(PlanFeatureGate::class)->allowsRoute($workspace->id, 'user.commerce.pos.index'))->toBeTrue();
});

it('excludes blocked items from global search', function (): void {
    [$user] = workspaceOnPlanBlocking(['commerce:commerce.pos.index']);

    $request = Request::create('/dashboard');
    $request->setRouteResolver(fn () => null);
    $ids = collect(app(WorkspaceNavigationRegistry::class)->searchableGroups($user, $request))
        ->flatMap(fn (array $group): array => array_column($group['items'], 'id'));

    expect($ids)->not->toContain('user.commerce.pos.index')
        ->and($ids)->toContain('user.commerce.orders.index');
});

it('flags team permissions whose features are outside the plan', function (): void {
    [, $workspace] = workspaceOnPlanBlocking(['commerce']);
    $gate = app(PlanFeatureGate::class);

    expect($gate->includesPermission($workspace->id, 'commerce.pos.use'))->toBeFalse()
        ->and($gate->includesPermission($workspace->id, 'inbox.view'))->toBeTrue()
        ->and($gate->includesPermission($workspace->id, 'workspace.manage'))->toBeTrue();
});

it('lets an admin choose the modules and items a plan includes', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Starter', 'slug' => 'starter', 'price' => 10, 'interval' => Plan::INTERVAL_MONTH,
        'limits' => [], 'features' => [], 'is_active' => true, 'sort_order' => 1,
    ]);
    $catalog = app(WorkspaceNavigationRegistry::class)->featureCatalog();
    $modules = ['inbox', 'crm', 'marketing', 'meta', 'automation', 'channels', 'commerce'];
    $items = collect($catalog)
        ->except('dashboard')
        ->flatMap(fn (array $module): array => array_keys($module['items']))
        ->reject(fn (string $key): bool => $key === 'commerce:commerce.pos.index')
        ->values()
        ->all();
    $modules = array_values(array_diff($modules, ['meta']));
    $middleware = [PanelAccess::class, EnsureTwoFactorAuthenticated::class, PermissionMiddleware::class, Authorize::class];

    $this->withoutMiddleware($middleware)->actingAs(Admin::factory()->create(), 'admin')
        ->put(route('admin.plans.update', $plan), [
            'name' => 'Starter', 'slug' => 'starter', 'price' => 10, 'interval' => 'month', 'sort_order' => 1, 'is_active' => '1',
            'features_access' => ['submitted' => '1', 'modules' => $modules, 'items' => $items],
        ])
        ->assertRedirect(route('admin.plans.index'));

    $plan->refresh();

    expect($plan->blockedFeatures())->toEqualCanonicalizing(['meta', 'commerce:commerce.pos.index'])
        ->and($plan->includesFeature('commerce', 'commerce:commerce.orders.index'))->toBeTrue()
        ->and($plan->includesFeature('meta'))->toBeFalse();

    $this->withoutMiddleware($middleware)->actingAs(Admin::factory()->create(), 'admin')
        ->put(route('admin.plans.update', $plan), [
            'name' => 'Starter', 'slug' => 'starter', 'price' => 10, 'interval' => 'month', 'sort_order' => 1,
            'features_access' => ['submitted' => '1', 'modules' => ['not-a-module']],
        ])
        ->assertSessionHasErrors('features_access.modules.0');

    $this->withoutMiddleware($middleware)->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.plans.edit', $plan))
        ->assertOk()
        ->assertSee('Feature Access')
        ->assertSee('features_access[items][]', false);

    $this->withoutMiddleware($middleware)->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.plans.index'))
        ->assertOk()
        ->assertSee('6 / 7 modules');
});
