<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Modules\Commerce\Models\Category;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the first workspace, create one if it doesn't exist
        $workspace = Workspace::first();
        if (!$workspace) {
            $workspace = Workspace::create([
                'name' => 'Default Workspace',
                'slug' => 'default-workspace'
            ]);
        }
        $workspaceId = $workspace->id;

        $categories = [
            'Men' => [
                'Tops' => [
                    'T-Shirts', 'Casual Shirts', 'Formal Shirts', 'Polos', 'Jackets & Coats', 'Hoodies & Sweatshirts'
                ],
                'Bottoms' => [
                    'Jeans', 'Trousers', 'Shorts', 'Joggers & Trackpants'
                ],
                'Activewear' => [],
                'Innerwear & Sleepwear' => [],
            ],
            'Women' => [
                'Tops' => [
                    'T-Shirts', 'Blouses & Shirts', 'Tops & Tunics', 'Jackets & Coats', 'Sweaters & Cardigans'
                ],
                'Bottoms' => [
                    'Jeans', 'Leggings', 'Skirts', 'Trousers', 'Shorts'
                ],
                'Dresses & Jumpsuits' => [],
                'Activewear' => [],
                'Lingerie & Sleepwear' => [],
            ],
            'Kids' => [
                'Boys' => [
                    'Tops', 'Bottoms', 'Tracksuits'
                ],
                'Girls' => [
                    'Tops', 'Bottoms', 'Dresses'
                ],
                'Infants & Toddlers' => [],
            ],
            'Unisex' => [
                'T-Shirts', 'Hoodies', 'Outerwear'
            ],
            'Accessories' => [
                'Hats & Caps', 'Belts', 'Socks', 'Bags & Backpacks'
            ]
        ];

        // Disable foreign key checks to truncate properly
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Category::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->createCategories($categories, null, '', $workspaceId);
    }

    /**
     * Recursively create categories and maintain unique hierarchical slugs.
     */
    private function createCategories(array $categories, $parentId = null, $parentSlug = '', $workspaceId = 1)
    {
        foreach ($categories as $key => $value) {
            $name = is_string($key) ? $key : $value;
            
            // Build unique hierarchical slug
            $slugBase = $parentSlug ? $parentSlug . '-' . Str::slug($name) : Str::slug($name);
            
            // Just in case, ensure it's not too long for the DB (max 255)
            $slugBase = substr($slugBase, 0, 255);

            $category = Category::create([
                'workspace_id' => $workspaceId,
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slugBase,
                'is_active' => true,
            ]);

            if (is_array($value) && count($value) > 0) {
                $this->createCategories($value, $category->id, $slugBase, $workspaceId);
            }
        }
    }
}
