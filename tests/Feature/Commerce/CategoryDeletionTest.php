<?php

use App\Models\User;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('commerce.manage', 'web');
    Permission::findOrCreate('commerce.view', 'web');

    $this->merchant = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->merchant);
    $this->merchant->givePermissionTo(['commerce.manage', 'commerce.view']);
    $this->actingAs($this->merchant);
});

it('deletes parent categories without products and keeps child categories', function (): void {
    $parent = Category::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Kids',
        'slug' => 'kids',
        'is_active' => true,
    ]);
    $child = Category::query()->create([
        'workspace_id' => $this->workspace->id,
        'parent_id' => $parent->id,
        'name' => 'Boys',
        'slug' => 'boys',
        'is_active' => true,
    ]);

    $this->delete(route('user.commerce.categories.destroy', $parent))
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('Category deleted.'));

    expect(Category::query()->whereKey($parent->id)->exists())->toBeFalse()
        ->and($child->fresh()->parent_id)->toBeNull();
});

it('does not delete categories that have products', function (): void {
    $category = Category::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Tops',
        'slug' => 'tops',
        'is_active' => true,
    ]);
    Product::query()->create([
        'workspace_id' => $this->workspace->id,
        'category_id' => $category->id,
        'name' => 'Cotton Tee',
        'slug' => 'cotton-tee',
    ]);

    $this->delete(route('user.commerce.categories.destroy', $category))
        ->assertRedirect()
        ->assertSessionHasErrors('category');

    expect($category->fresh())->not->toBeNull();
});
