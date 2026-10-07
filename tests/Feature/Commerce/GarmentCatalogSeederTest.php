<?php

use App\Modules\Commerce\Database\Seeders\GarmentCatalogSeeder;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Workspaces\Models\Workspace;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function garmentWorkspace(string $slug = 'garments'): Workspace
{
    return Workspace::query()->create(['name' => $slug, 'slug' => $slug]);
}

function garmentCategory(Workspace $workspace, string $name, string $slug, ?Category $parent = null): Category
{
    return Category::query()->create([
        'workspace_id' => $workspace->id, 'name' => $name, 'slug' => $slug,
        'parent_id' => $parent?->id, 'is_active' => true,
    ]);
}

it('adds the complete garment hierarchy and brands to each workspace without duplicates', function (): void {
    $workspaces = [garmentWorkspace(), garmentWorkspace('second')];
    $this->seed(CategorySeeder::class);
    $snapshot = Category::query()->orderBy('id')->get()->map->getAttributes()->all();
    $brands = Brand::query()->orderBy('id')->get()->map->getAttributes()->all();
    $this->seed(GarmentCatalogSeeder::class);
    expect(Category::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($snapshot)
        ->and(Brand::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($brands);

    foreach ($workspaces as $workspace) {
        $assertTree = function (array $tree, ?Category $parent = null, string $prefix = '') use (&$assertTree, $workspace): int {
            $count = 0;
            foreach ($tree as $key => $value) {
                $name = is_string($key) ? $key : $value;
                $slug = $prefix === '' ? str($name)->slug()->toString() : $prefix.'-'.str($name)->slug();
                $category = Category::query()->where('workspace_id', $workspace->id)->where('slug', $slug)->sole();
                expect($category->name)->toBe($name)->and($category->parent_id)->toBe($parent?->id)
                    ->and($category->is_active)->toBeTrue()->and($category->image)->toBeNull();
                $count++;
                if (is_array($value)) {
                    $count += $assertTree($value, $category, $slug);
                }
            }

            return $count;
        };
        expect(Category::query()->where('workspace_id', $workspace->id)->count())->toBe($assertTree(GarmentCatalogSeeder::CATEGORIES));
        expect(Brand::query()->where('workspace_id', $workspace->id)->pluck('name')->all())->toEqualCanonicalizing(GarmentCatalogSeeder::BRANDS);
        expect(Category::query()->where('workspace_id', $workspace->id)->whereIn('name', ['Sport', 'Shoes', 'Shoes & Sneakers', 'Accessories', 'Activewear'])->exists())->toBeFalse();
    }
});

it('preserves customized categories brands and product references while extending legacy jackets', function (): void {
    $workspace = garmentWorkspace();
    $men = garmentCategory($workspace, 'Men', 'men');
    $tops = garmentCategory($workspace, 'Tops', 'custom-tops', $men);
    $jackets = garmentCategory($workspace, 'Jackets & Coats', 'legacy-jackets', $tops);
    $jackets->update(['image' => 'custom.webp', 'is_active' => false]);
    $sports = garmentCategory($workspace, 'Sport', 'sport');
    $brand = Brand::query()->create(['workspace_id' => $workspace->id, 'name' => 'Nike', 'slug' => 'custom-nike', 'is_active' => false]);
    $product = Product::query()->create(['workspace_id' => $workspace->id, 'name' => 'Jacket', 'slug' => 'jacket', 'category_id' => $jackets->id, 'brand_id' => $brand->id]);
    $before = $jackets->fresh()->getAttributes();
    $brandBefore = $brand->fresh()->getAttributes();
    $this->seed(GarmentCatalogSeeder::class);
    $this->seed(GarmentCatalogSeeder::class);
    expect($jackets->fresh()->getAttributes())->toBe($before)
        ->and($brand->fresh()->getAttributes())->toBe($brandBefore)
        ->and($product->fresh()->category_id)->toBe($jackets->id)
        ->and($product->fresh()->brand_id)->toBe($brand->id)
        ->and($sports->fresh())->not->toBeNull()
        ->and($jackets->children()->where('name', 'Full-Zip Jacket')->count())->toBe(1)
        ->and(Category::query()->where('workspace_id', $workspace->id)->where('name', 'Jackets & Coats')->whereHas('parent', fn ($query) => $query->where('name', 'Outerwear')->where('parent_id', $men->id))->count())->toBe(0);
});

it('rejects category slug conflicts and rolls back additions in that workspace', function (bool $wrongParent): void {
    $workspace = garmentWorkspace();
    $men = garmentCategory($workspace, 'Men', 'men');
    $other = garmentCategory($workspace, 'Other', 'other');
    garmentCategory($workspace, $wrongParent ? 'Tops' : 'Custom Collection', 'men-tops', $wrongParent ? $other : $men);
    $before = Category::query()->count();
    expect(fn () => $this->seed(GarmentCatalogSeeder::class))->toThrow(RuntimeException::class, 'Category slug conflict');
    expect(Category::query()->count())->toBe($before)->and(Brand::query()->count())->toBe(0);
})->with([false, true]);

it('rejects conflicting brand slugs without leaving partial categories', function (): void {
    $workspace = garmentWorkspace();
    Brand::query()->create(['workspace_id' => $workspace->id, 'name' => 'Custom Brand', 'slug' => 'nike', 'is_active' => true]);
    expect(fn () => $this->seed(GarmentCatalogSeeder::class))->toThrow(RuntimeException::class, 'Brand slug conflict');
    expect(Category::query()->count())->toBe(0)->and(Brand::query()->count())->toBe(1);
});

it('runs the data migration repeatedly and retains data on rollback', function (): void {
    garmentWorkspace();
    $migration = require glob(app_path('Modules/Commerce/Database/Migrations/*_seed_garment_categories_and_brands.php'))[0];
    $migration->up();
    $categories = Category::query()->orderBy('id')->get()->map->getAttributes()->all();
    $brands = Brand::query()->orderBy('id')->get()->map->getAttributes()->all();
    $migration->up();
    $migration->down();
    expect(Category::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($categories)
        ->and(Brand::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($brands);
});

it('does not create a workspace when none exist', function (): void {
    $this->seed(CategorySeeder::class);
    expect(Workspace::query()->count())->toBe(0)
        ->and(Category::query()->count())->toBe(0)
        ->and(Brand::query()->count())->toBe(0);
});

it('reuses existing demo garment names when retrying the production migration', function (): void {
    $workspace = garmentWorkspace();
    $men = garmentCategory($workspace, 'Men', 'men');
    $tops = garmentCategory($workspace, 'Tops', 'men-tops', $men);
    $bottoms = garmentCategory($workspace, 'Bottoms', 'men-bottoms', $men);
    $legacy = [
        garmentCategory($workspace, 'Polo Shirts', 'men-tops-polos', $tops),
        garmentCategory($workspace, 'Hoodies & Sweaters', 'men-tops-hoodies-sweatshirts', $tops),
        garmentCategory($workspace, 'Denim & Jeans', 'men-bottoms-jeans', $bottoms),
    ];
    $snapshots = array_map(fn (Category $category) => $category->fresh()->getAttributes(), $legacy);
    $migration = require glob(app_path('Modules/Commerce/Database/Migrations/*_seed_garment_categories_and_brands.php'))[0];
    $migration->up();
    $migration->up();
    foreach ($legacy as $index => $category) {
        expect($category->fresh()->getAttributes())->toBe($snapshots[$index]);
    }
    expect(Category::query()->where('workspace_id', $workspace->id)->whereIn('name', ['Polos', 'Hoodies & Sweatshirts', 'Jeans'])->whereIn('parent_id', [$tops->id, $bottoms->id])->exists())->toBeFalse();
});
