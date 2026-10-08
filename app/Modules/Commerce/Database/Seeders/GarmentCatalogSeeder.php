<?php

namespace App\Modules\Commerce\Database\Seeders;

use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class GarmentCatalogSeeder extends Seeder
{
    public const BRANDS = ['NikeSKIMS', 'Nike Sportswear', 'ACG', 'Jordan', 'Kobe', 'Nike', 'Adidas', 'Puma', 'Reebok', 'New Balance', 'Converse', 'Vans', 'Under Armour', 'ASICS'];

    public const CATEGORIES = [
        'Men' => [
            'Tops' => ['T-Shirts', 'Casual Shirts', 'Formal Shirts', 'Polos', 'Hoodies & Sweatshirts', 'Sweaters'],
            'Outerwear' => ['Jackets & Coats' => ['Full-Zip Jacket']],
            'Bottoms' => ['Jeans', 'Trousers', 'Shorts', 'Joggers & Trackpants'],
            'Innerwear & Sleepwear' => ['Underwear', 'Vests', 'Pajamas'],
        ],
        'Women' => [
            'Tops' => ['T-Shirts', 'Blouses & Shirts', 'Tops & Tunics', 'Sweaters & Cardigans', 'Hoodies & Sweatshirts'],
            'Outerwear' => ['Jackets & Coats'],
            'Bottoms' => ['Jeans', 'Trousers', 'Leggings', 'Skirts', 'Shorts'],
            'Dresses & Jumpsuits' => ['Dresses', 'Jumpsuits'],
            'Matching Sets',
            'Lingerie & Sleepwear' => ['Underwear', 'Bras', 'Pajamas', 'Nightwear'],
        ],
        'Kids' => [
            'Boys' => ['Tops', 'Bottoms', 'Tracksuits', 'Jackets & Coats', 'Sleepwear'],
            'Girls' => ['Tops', 'Bottoms', 'Dresses', 'Jackets & Coats', 'Sleepwear'],
            'Infants & Toddlers' => ['Bodysuits', 'Rompers', 'Clothing Sets', 'Sleepwear'],
        ],
        'Unisex' => ['T-Shirts', 'Hoodies', 'Outerwear'],
    ];

    public function run(): void
    {
        Workspace::query()->chunkById(100, function (Collection $workspaces): void {
            foreach ($workspaces as $workspace) {
                $workspace->getConnection()->transaction(function () use ($workspace): void {
                    $this->seedCategories($workspace->id, self::CATEGORIES);
                    foreach (self::BRANDS as $name) {
                        $slug = Str::slug($name);
                        $brand = Brand::query()->where('workspace_id', $workspace->id)->where('slug', $slug)->first();
                        if ($brand && strcasecmp($brand->name, $name) !== 0) {
                            throw new RuntimeException("Brand slug conflict in workspace {$workspace->id}: {$slug}");
                        }
                        $brand ??= Brand::query()->where('workspace_id', $workspace->id)->where('name', $name)->first();
                        if (! $brand) {
                            Brand::query()->create(['workspace_id' => $workspace->id, 'name' => $name, 'slug' => $slug, 'is_active' => true]);
                        }
                    }
                });
            }
        });
    }

    /** @param array<int|string, mixed> $definitions */
    private function seedCategories(int $workspaceId, array $definitions, ?Category $parent = null, string $parentSlug = ''): void
    {
        foreach ($definitions as $key => $value) {
            $name = is_string($key) ? $key : $value;
            $slug = $parentSlug === '' ? Str::slug($name) : $parentSlug.'-'.Str::slug($name);
            $names = match ($slug) {
                'men-tops-polos' => [$name, 'Polo Shirts'],
                'men-tops-hoodies-sweatshirts' => [$name, 'Hoodies & Sweaters'],
                'men-bottoms-jeans' => [$name, 'Denim & Jeans'],
                default => [$name],
            };
            $category = Category::query()->where('workspace_id', $workspaceId)->where('slug', $slug)->first();
            if ($category && (! in_array(strtolower($category->name), array_map('strtolower', $names), true) || $category->parent_id !== $parent?->id)) {
                throw new RuntimeException("Category slug conflict in workspace {$workspaceId}: {$slug}");
            }
            $category ??= Category::query()->where('workspace_id', $workspaceId)->where('parent_id', $parent?->id)->whereIn('name', $names)->first();
            if (! $category && $name === 'Jackets & Coats' && $parent?->name === 'Outerwear') {
                $category = Category::query()->where('workspace_id', $workspaceId)->where('name', $name)
                    ->whereHas('parent', fn ($query) => $query->where('name', 'Tops')->where('parent_id', $parent->parent_id))->first();
            }
            $category ??= Category::query()->create([
                'workspace_id' => $workspaceId, 'parent_id' => $parent?->id,
                'name' => $name, 'slug' => $slug, 'is_active' => true,
            ]);
            if (is_array($value)) {
                $this->seedCategories($workspaceId, $value, $category, $category->slug);
            }
        }
    }
}
