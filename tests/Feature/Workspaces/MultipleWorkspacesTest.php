<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureSubscriptionUsable;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware([
        EnsureOnboardingComplete::class,
        EnsureSubscriptionUsable::class,
        PanelAccess::class,
        EnsureTwoFactorAuthenticated::class,
    ]);
    Gate::before(fn ($user, $ability) => $ability === 'settings.view' ? true : null);
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::query()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Existing workspace',
        'slug' => 'existing-'.Str::uuid(),
        'status' => WorkspaceStatus::Active,
        'timezone' => 'UTC',
    ]);
});

it('creates multiple active workspaces with owner membership and roles', function (): void {
    $this->actingAs($this->owner);
    for ($index = 0; $index < 3; $index++) {
        $slug = 'additional-'.Str::uuid();
        $this->post(route('user.workspaces.store'), ['name' => 'Additional workspace', 'slug' => $slug])
            ->assertRedirect(route('user.workspaces.index'))->assertSessionHasNoErrors();
        $workspace = Workspace::query()->where('slug', $slug)->firstOrFail();
        expect($workspace->activeMembers()->whereKey($this->owner->id)->exists())->toBeTrue();
        $this->assertDatabaseHas('workspace_roles', ['workspace_id' => $workspace->id, 'name' => 'Administrator']);
        $this->assertDatabaseHas('workspace_roles', ['workspace_id' => $workspace->id, 'name' => 'Manager']);
        $this->assertDatabaseHas('workspace_roles', ['workspace_id' => $workspace->id, 'name' => 'Staff']);
        expect(session('active_workspace_id'))->toBe($workspace->id);
    }
    expect(Workspace::query()->where('owner_id', $this->owner->id)->where('status', 'active')->count())->toBe(4);
    $this->post(route('user.workspaces.switch', $this->workspace))
        ->assertRedirect(route('user.dashboard'))->assertSessionHas('active_workspace_id', $this->workspace->id);
    expect(Workspace::query()->where('owner_id', $this->owner->id)->where('status', 'active')->count())->toBe(4);
});

it('activates another workspace without suspending existing workspaces', function (): void {
    $other = Workspace::query()->create(['owner_id' => $this->owner->id, 'name' => 'Suspended', 'slug' => 'suspended-'.Str::uuid(), 'status' => 'suspended']);
    $this->actingAs($this->owner)->patch(route('user.workspaces.toggle-status', $other))
        ->assertRedirect(route('user.workspaces.index'));
    expect($this->workspace->fresh()->status)->toBe(WorkspaceStatus::Active)
        ->and($other->fresh()->status)->toBe(WorkspaceStatus::Active);
});

it('rejects activation of another owners workspace', function (): void {
    $this->actingAs(User::factory()->create())->patch(route('user.workspaces.toggle-status', $this->workspace))->assertForbidden();
    expect($this->workspace->fresh()->status)->toBe(WorkspaceStatus::Active);
});

it('skips unavailable service tables without querying them', function (): void {
    $schema = Mockery::mock(Builder::class);
    $schema->shouldReceive('hasTable')->andReturn(false);
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
    $connection->shouldNotReceive('table');
    $workspace = Mockery::mock(Workspace::class)->makePartial();
    $workspace->shouldReceive('getConnection')->andReturn($connection);
    expect($workspace->canDelete())->toBeTrue();
});

it('protects commerce contents and scopes service checks to each workspace', function (): void {
    $empty = Workspace::query()->create(['owner_id' => $this->owner->id, 'name' => 'Empty', 'slug' => 'empty-'.Str::uuid(), 'status' => 'active']);
    Product::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Stored product', 'slug' => 'product-'.Str::uuid()]);
    expect($this->workspace->canDelete())->toBeFalse()->and($empty->canDelete())->toBeTrue();
    $this->actingAs($this->owner)->delete(route('user.workspaces.destroy', $this->workspace))->assertForbidden();
});

it('renders the workspace list when optional billing tables are unavailable', function (): void {
    $this->actingAs($this->owner)->get(route('user.workspaces.index'))
        ->assertOk()->assertSee('Existing workspace')->assertSee('Create Workspace')
        ->assertDontSee('Workspace URL')->assertDontSee('name="slug"', false)->assertDontSee($this->workspace->slug);
});

it('generates unique internal identifiers when creating workspaces without a URL', function (string $name): void {
    $this->actingAs($this->owner);
    for ($index = 0; $index < 2; $index++) {
        $this->post(route('user.workspaces.store'), ['name' => $name, 'timezone' => 'UTC'])
            ->assertRedirect(route('user.workspaces.index'))->assertSessionHasNoErrors();
    }
    $workspaces = Workspace::query()->where('owner_id', $this->owner->id)->where('name', $name)->get();
    expect($workspaces)->toHaveCount(2)->and($workspaces->pluck('slug')->unique())->toHaveCount(2);
    foreach ($workspaces as $workspace) {
        expect(strlen($workspace->slug))->toBeLessThanOrEqual(100)->and($workspace->slug)->toMatch('/^[a-z0-9-]+$/');
    }
})->with(['Downtown Roasters', str_repeat('Long workspace name ', 10), '🏢']);
