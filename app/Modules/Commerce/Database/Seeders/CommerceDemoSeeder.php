<?php

namespace App\Modules\Commerce\Database\Seeders;

use App\Modules\Commerce\Models\Audience;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductOption;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CommerceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->with('owner')->orderBy('id')->first();
        if (! $workspace) {
            throw new RuntimeException('Create a workspace before running the Commerce demo seeder.');
        }

        DB::transaction(function () use ($workspace): void {
            $brands = $this->brands($workspace->id);
            $audiences = $this->audiences($workspace->id);
            $categories = $this->categories($workspace->id);
            $media = $this->media((int) $workspace->owner_id);

            foreach ($this->products() as $index => $definition) {
                $this->seedProduct($workspace->id, $index, $definition, $brands, $audiences, $categories, $media);
            }
        });
    }

    /** @return array<string, Brand> */
    protected function brands(int $workspaceId): array
    {
        return collect(['Dhaka Loom', 'Bengal Thread', 'River & Reed', 'Northstar Apparel', 'Urban Weave', 'Cotton House', 'Aarong Lane', 'Summit Active'])
            ->mapWithKeys(function (string $name) use ($workspaceId): array {
                $brand = Brand::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
                    ['name' => $name, 'is_active' => true]
                );

                return [$name => $brand];
            })->all();
    }

    /** @return array<string, Audience> */
    protected function audiences(int $workspaceId): array
    {
        return collect(['Women', 'Men', 'Unisex', 'Kids', 'Teen', 'Baby'])
            ->mapWithKeys(function (string $name) use ($workspaceId): array {
                $audience = Audience::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
                    ['name' => $name, 'is_active' => true]
                );

                return [$name => $audience];
            })->all();
    }

    /** @return array<string, Category> */
    protected function categories(int $workspaceId): array
    {
        return collect(['Shirts', 'Trousers', 'Dresses', 'Jackets', 'Activewear', 'Kids Clothing', 'Uniforms', 'Hoodies & Sweaters', 'Coats', 'Blouses'])
            ->mapWithKeys(function (string $name) use ($workspaceId): array {
                $category = Category::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
                    ['name' => $name, 'parent_id' => null, 'is_active' => true]
                );

                return [$name => $category];
            })->all();
    }

    /** @return array<int, Media> */
    protected function media(int $userId): array
    {
        $keywords = ['shirt', 'dress', 'jacket', 'trousers', 'activewear', 'hoodie', 'sweater', 'coat', 'blouse', 'uniform'];

        return collect(range(1, 60))->mapWithKeys(function (int $number) use ($keywords, $userId): array {
            $keyword = $keywords[($number - 1) % count($keywords)];
            $url = "https://loremflickr.com/960/1200/fashion,{$keyword}?lock=".(1000 + $number);
            $media = Media::query()->updateOrCreate(
                ['file_name' => sprintf('commerce-demo-%02d.jpg', $number), 'uploaded_by' => $userId],
                [
                    'name' => sprintf('Commerce fashion image %02d', $number),
                    'original_name' => sprintf('commerce-demo-%02d.jpg', $number),
                    'mime_type' => 'image/jpeg',
                    'extension' => 'jpg',
                    'type' => 'image',
                    'size' => 350000,
                    'disk' => 'public',
                    'path' => $url,
                    'alt' => ucfirst($keyword).' apparel product image',
                ]
            );

            return [$number => $media];
        })->all();
    }

    /**
     * @return array<int, array{name: string, category: string, audience: string, material: string, base_price: float}>
     */
    protected function products(): array
    {
        $styles = [
            ['name' => 'Oxford Shirt', 'category' => 'Shirts', 'audience' => 'Men', 'material' => 'Cotton', 'base_price' => 34.00],
            ['name' => 'Linen Shirt', 'category' => 'Shirts', 'audience' => 'Unisex', 'material' => 'Linen', 'base_price' => 39.00],
            ['name' => 'Polo Shirt', 'category' => 'Shirts', 'audience' => 'Unisex', 'material' => 'Pique Cotton', 'base_price' => 29.00],
            ['name' => 'Chino Trousers', 'category' => 'Trousers', 'audience' => 'Men', 'material' => 'Cotton Twill', 'base_price' => 44.00],
            ['name' => 'Tailored Trousers', 'category' => 'Trousers', 'audience' => 'Women', 'material' => 'Viscose Blend', 'base_price' => 52.00],
            ['name' => 'Maxi Dress', 'category' => 'Dresses', 'audience' => 'Women', 'material' => 'Viscose', 'base_price' => 58.00],
            ['name' => 'Wrap Dress', 'category' => 'Dresses', 'audience' => 'Women', 'material' => 'Crepe', 'base_price' => 54.00],
            ['name' => 'Denim Jacket', 'category' => 'Jackets', 'audience' => 'Unisex', 'material' => 'Denim', 'base_price' => 69.00],
            ['name' => 'Bomber Jacket', 'category' => 'Jackets', 'audience' => 'Men', 'material' => 'Recycled Nylon', 'base_price' => 76.00],
            ['name' => 'Performance Leggings', 'category' => 'Activewear', 'audience' => 'Women', 'material' => 'Stretch Jersey', 'base_price' => 38.00],
            ['name' => 'Training Shorts', 'category' => 'Activewear', 'audience' => 'Unisex', 'material' => 'Quick-dry Polyester', 'base_price' => 32.00],
            ['name' => 'Kids Zip Hoodie', 'category' => 'Kids Clothing', 'audience' => 'Kids', 'material' => 'Cotton Fleece', 'base_price' => 31.00],
            ['name' => 'School Uniform Shirt', 'category' => 'Uniforms', 'audience' => 'Kids', 'material' => 'Cotton Poplin', 'base_price' => 24.00],
            ['name' => 'Workwear Coverall', 'category' => 'Uniforms', 'audience' => 'Unisex', 'material' => 'Durable Twill', 'base_price' => 64.00],
            ['name' => 'Fleece Hoodie', 'category' => 'Hoodies & Sweaters', 'audience' => 'Unisex', 'material' => 'Brushed Fleece', 'base_price' => 48.00],
            ['name' => 'Cable Knit Sweater', 'category' => 'Hoodies & Sweaters', 'audience' => 'Women', 'material' => 'Cotton Knit', 'base_price' => 57.00],
            ['name' => 'Classic Trench Coat', 'category' => 'Coats', 'audience' => 'Women', 'material' => 'Cotton Gabardine', 'base_price' => 98.00],
            ['name' => 'Wool Blend Coat', 'category' => 'Coats', 'audience' => 'Men', 'material' => 'Wool Blend', 'base_price' => 112.00],
            ['name' => 'Pleated Cotton Blouse', 'category' => 'Blouses', 'audience' => 'Women', 'material' => 'Cotton Voile', 'base_price' => 42.00],
            ['name' => 'Cargo Pants', 'category' => 'Trousers', 'audience' => 'Teen', 'material' => 'Ripstop Cotton', 'base_price' => 49.00],
        ];
        $collections = ['Essential', 'Heritage', 'Urban', 'Studio', 'Premium'];

        return collect($collections)->flatMap(fn (string $collection, int $collectionIndex) => collect($styles)->map(fn (array $style): array => array_merge($style, [
            'name' => $collection.' '.$style['name'],
            'base_price' => $style['base_price'] + ($collectionIndex * 4),
        ])))->values()->all();
    }

    /**
     * @param  array{name: string, category: string, audience: string, material: string, base_price: float}  $definition
     * @param  array<string, Brand>  $brands
     * @param  array<string, Audience>  $audiences
     * @param  array<string, Category>  $categories
     * @param  array<int, Media>  $media
     */
    protected function seedProduct(int $workspaceId, int $index, array $definition, array $brands, array $audiences, array $categories, array $media): void
    {
        $number = $index + 1;
        $brand = array_values($brands)[$index % count($brands)];
        $audience = $audiences[$definition['audience']];
        $primaryMedia = $media[(($index * 7) % 60) + 1];
        $secondaryMedia = $media[(($index * 7 + 19) % 60) + 1];
        $price = round($definition['base_price'], 2);
        $slug = 'demo-'.Str::slug($definition['name']);
        $product = Product::query()->updateOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => $slug],
            [
                'category_id' => $categories[$definition['category']]->id,
                'brand_id' => $brand->id,
                'audience_id' => $audience->id,
                'primary_media_id' => $primaryMedia->id,
                'name' => $definition['name'],
                'brand' => $brand->name,
                'description' => "A production-ready {$definition['name']} made from {$definition['material']}. Designed for dependable everyday wear, clean presentation, and Bangladesh-to-US wholesale or retail orders.",
                'care_information' => 'Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.',
                'condition' => 'new',
                'audience' => $audience->name,
                'country_of_origin' => 'BD',
                'status' => 'active',
                'wizard_step' => 5,
                'published_at' => now(),
            ]
        );

        ProductMedia::query()->updateOrCreate(
            ['product_id' => $product->id, 'media_id' => $primaryMedia->id],
            ['workspace_id' => $workspaceId, 'media_type' => 'image', 'role' => 'primary', 'alt_text' => $definition['name'].' front view', 'position' => 0, 'is_primary' => true]
        );
        ProductMedia::query()->updateOrCreate(
            ['product_id' => $product->id, 'media_id' => $secondaryMedia->id],
            ['workspace_id' => $workspaceId, 'media_type' => 'image', 'role' => 'gallery', 'alt_text' => $definition['name'].' detail view', 'position' => 1, 'is_primary' => false]
        );

        $sizes = $definition['audience'] === 'Kids' ? ['4Y', '8Y'] : ['S', 'M'];
        $colors = $index % 2 === 0 ? ['Black', 'Sand'] : ['Navy', 'Olive'];
        $sizeOption = $this->option($workspaceId, $product->id, 'Size', 'size', 0, $sizes);
        $colorOption = $this->option($workspaceId, $product->id, 'Color', 'color', 1, $colors);

        foreach ($sizes as $sizeIndex => $size) {
            foreach ($colors as $colorIndex => $color) {
                $suffix = Str::upper(Str::slug($size.'-'.$color, '-'));
                ProductVariant::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'sku' => sprintf('DEMO-%03d-%s', $number, $suffix)],
                    [
                        'product_id' => $product->id,
                        'media_id' => $colorIndex === 0 ? $primaryMedia->id : $secondaryMedia->id,
                        'meta_retailer_id' => sprintf('demo-%03d-%s', $number, Str::lower($suffix)),
                        'attributes' => ['size' => $size, 'color' => $color, 'material' => $definition['material']],
                        'price' => $price + ($sizeIndex * 2),
                        'compare_at_price' => $price + 12 + ($sizeIndex * 2),
                        'stock_quantity' => 12 + (($index + $sizeIndex + $colorIndex) % 29),
                        'weight_kg' => $definition['category'] === 'Coats' ? 1.2 : 0.45,
                        'package_dimensions' => ['length_cm' => 35, 'width_cm' => 28, 'height_cm' => 6],
                        'status' => 'active',
                    ]
                );
            }
        }

        $sizeOption->touch();
        $colorOption->touch();
    }

    /** @param array<int, string> $values */
    protected function option(int $workspaceId, int $productId, string $name, string $code, int $position, array $values): ProductOption
    {
        $option = ProductOption::query()->updateOrCreate(
            ['product_id' => $productId, 'code' => $code],
            ['workspace_id' => $workspaceId, 'name' => $name, 'position' => $position]
        );
        foreach ($values as $valuePosition => $value) {
            $option->values()->updateOrCreate(
                ['value' => $value],
                ['workspace_id' => $workspaceId, 'position' => $valuePosition]
            );
        }

        return $option;
    }
}
