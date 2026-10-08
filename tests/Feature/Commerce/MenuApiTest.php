<?php

use App\Models\User;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->workspace = app(WorkspaceResolver::class)->current(User::factory()->create());
});

function menuCategory(int $workspaceId, string $name, ?Category $parent = null, bool $isActive = true): Category
{
    return Category::create([
        'workspace_id' => $workspaceId,
        'parent_id' => $parent?->id,
        'name' => $name,
        'slug' => str($name)->slug().'-'.$workspaceId,
        'is_active' => $isActive,
    ]);
}

it('returns the active three level category tree and brands', function (): void {
    $men = menuCategory($this->workspace->id, 'Men');
    $shoes = menuCategory($this->workspace->id, 'Shoes', $men);
    menuCategory($this->workspace->id, 'Running', $shoes);
    menuCategory($this->workspace->id, 'Hidden', $shoes, false);
    menuCategory($this->workspace->id, 'Archived', $men, false);
    menuCategory($this->workspace->id, 'Retired', isActive: false);
    Brand::create(['workspace_id' => $this->workspace->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
    Brand::create(['workspace_id' => $this->workspace->id, 'name' => 'Gone', 'slug' => 'gone', 'is_active' => false]);

    $this->getJson('/api/commerce/menu?workspace_id='.$this->workspace->id)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.name', 'Men')
        ->assertJsonCount(1, 'data.categories.0.children')
        ->assertJsonPath('data.categories.0.children.0.name', 'Shoes')
        ->assertJsonCount(1, 'data.categories.0.children.0.children')
        ->assertJsonPath('data.categories.0.children.0.children.0.name', 'Running')
        ->assertJsonCount(1, 'data.brands')
        ->assertJsonPath('data.brands.0.slug', 'acme');
});

it('scopes the menu to the requested workspace', function (): void {
    $otherWorkspace = app(WorkspaceResolver::class)->current(User::factory()->create());
    menuCategory($otherWorkspace->id, 'Foreign');

    $this->getJson('/api/commerce/menu?workspace_id='.$this->workspace->id)
        ->assertSuccessful()
        ->assertJsonCount(0, 'data.categories');
});
