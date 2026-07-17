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
        return collect(['Dhaka Loom Studio', 'Bengal Threadworks', 'River & Reed Apparel', 'Northstar Garments', 'Urban Weave Co.', 'Cotton House BD', 'Aarong Lane Basics', 'Summit Activewear'])
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
        $images = [
            ['query' => 'fashion,oxford-shirt', 'alt' => 'Oxford shirt on a studio model'],
            ['query' => 'fashion,linen-shirt', 'alt' => 'Linen shirt product lifestyle photo'],
            ['query' => 'fashion,polo-shirt', 'alt' => 'Polo shirt retail product image'],
            ['query' => 'fashion,chino-trousers', 'alt' => 'Chino trousers on model'],
            ['query' => 'fashion,tailored-trousers', 'alt' => 'Tailored trousers studio product image'],
            ['query' => 'fashion,maxi-dress', 'alt' => 'Maxi dress lifestyle product image'],
            ['query' => 'fashion,wrap-dress', 'alt' => 'Wrap dress on model'],
            ['query' => 'fashion,denim-jacket', 'alt' => 'Denim jacket product photo'],
            ['query' => 'fashion,bomber-jacket', 'alt' => 'Bomber jacket streetwear product photo'],
            ['query' => 'fashion,leggings', 'alt' => 'Performance leggings activewear image'],
            ['query' => 'fashion,training-shorts', 'alt' => 'Training shorts activewear product image'],
            ['query' => 'fashion,kids-hoodie', 'alt' => 'Kids zip hoodie product photo'],
            ['query' => 'fashion,school-uniform', 'alt' => 'School uniform shirt product photo'],
            ['query' => 'fashion,workwear-coverall', 'alt' => 'Workwear coverall garment photo'],
            ['query' => 'fashion,fleece-hoodie', 'alt' => 'Fleece hoodie product image'],
            ['query' => 'fashion,knit-sweater', 'alt' => 'Cable knit sweater product photo'],
            ['query' => 'fashion,trench-coat', 'alt' => 'Classic trench coat fashion image'],
            ['query' => 'fashion,wool-coat', 'alt' => 'Wool blend coat product image'],
            ['query' => 'fashion,cotton-blouse', 'alt' => 'Cotton blouse on model'],
            ['query' => 'fashion,cargo-pants', 'alt' => 'Cargo pants product photo'],
            ['query' => 'fashion,button-shirt', 'alt' => 'Button shirt ecommerce image'],
            ['query' => 'fashion,summer-shirt', 'alt' => 'Summer shirt catalog photo'],
            ['query' => 'fashion,knit-polo', 'alt' => 'Knit polo shirt product image'],
            ['query' => 'fashion,pleated-trousers', 'alt' => 'Pleated trousers product image'],
            ['query' => 'fashion,wide-leg-trousers', 'alt' => 'Wide leg trousers catalog photo'],
            ['query' => 'fashion,shirt-dress', 'alt' => 'Shirt dress lifestyle photo'],
            ['query' => 'fashion,midi-dress', 'alt' => 'Midi dress studio product image'],
            ['query' => 'fashion,overshirt', 'alt' => 'Overshirt jacket product photo'],
            ['query' => 'fashion,utility-jacket', 'alt' => 'Utility jacket catalog photo'],
            ['query' => 'fashion,sports-bra', 'alt' => 'Activewear top product image'],
            ['query' => 'fashion,running-shorts', 'alt' => 'Running shorts on model'],
            ['query' => 'fashion,kids-cardigan', 'alt' => 'Kids cardigan product image'],
            ['query' => 'fashion,uniform-polo', 'alt' => 'Uniform polo product photo'],
            ['query' => 'fashion,chef-jacket', 'alt' => 'Chef jacket workwear photo'],
            ['query' => 'fashion,pullover-hoodie', 'alt' => 'Pullover hoodie product image'],
            ['query' => 'fashion,crewneck-sweater', 'alt' => 'Crewneck sweater product photo'],
            ['query' => 'fashion,parka-coat', 'alt' => 'Parka coat fashion product image'],
            ['query' => 'fashion,overcoat', 'alt' => 'Overcoat catalog image'],
            ['query' => 'fashion,silk-blouse', 'alt' => 'Silk blouse product photo'],
            ['query' => 'fashion,drawstring-pants', 'alt' => 'Drawstring pants catalog image'],
            ['query' => 'fashion,apparel-flatlay', 'alt' => 'Apparel flat lay product photo'],
            ['query' => 'fashion,clothing-rack', 'alt' => 'Retail clothing rack product image'],
            ['query' => 'fashion,garment-detail', 'alt' => 'Garment fabric detail image'],
            ['query' => 'fashion,studio-model', 'alt' => 'Studio model wearing apparel'],
            ['query' => 'fashion,ecommerce-clothing', 'alt' => 'Ecommerce apparel product photo'],
            ['query' => 'fashion,retail-shirt', 'alt' => 'Retail shirt product image'],
            ['query' => 'fashion,retail-dress', 'alt' => 'Retail dress product image'],
            ['query' => 'fashion,retail-jacket', 'alt' => 'Retail jacket product image'],
            ['query' => 'fashion,retail-trousers', 'alt' => 'Retail trousers product image'],
            ['query' => 'fashion,retail-activewear', 'alt' => 'Retail activewear product image'],
            ['query' => 'fashion,casualwear', 'alt' => 'Casualwear product image'],
            ['query' => 'fashion,business-casual', 'alt' => 'Business casual garment photo'],
            ['query' => 'fashion,streetwear', 'alt' => 'Streetwear product photo'],
            ['query' => 'fashion,minimal-clothing', 'alt' => 'Minimal clothing catalog image'],
            ['query' => 'fashion,outerwear', 'alt' => 'Outerwear fashion product image'],
            ['query' => 'fashion,womenswear', 'alt' => 'Womenswear product image'],
            ['query' => 'fashion,menswear', 'alt' => 'Menswear product image'],
            ['query' => 'fashion,kidswear', 'alt' => 'Kidswear product image'],
            ['query' => 'fashion,uniforms', 'alt' => 'Uniform apparel product image'],
            ['query' => 'fashion,wholesale-clothing', 'alt' => 'Wholesale apparel product image'],
        ];

        return collect($images)->mapWithKeys(function (array $image, int $index) use ($userId): array {
            $number = $index + 1;
            $url = "https://loremflickr.com/960/1200/{$image['query']}?lock=".(4100 + $number);
            $media = Media::query()->updateOrCreate(
                ['file_name' => sprintf('commerce-demo-%02d.jpg', $number), 'uploaded_by' => $userId],
                [
                    'name' => sprintf('Commerce product image %02d', $number),
                    'original_name' => sprintf('commerce-demo-%02d.jpg', $number),
                    'mime_type' => 'image/jpeg',
                    'extension' => 'jpg',
                    'type' => 'image',
                    'size' => 350000,
                    'disk' => 'public',
                    'path' => $url,
                    'alt' => $image['alt'],
                ]
            );

            return [$number => $media];
        })->all();
    }

    /**
     * @return array<int, array{name: string, category: string, audience: string, material: string, base_price: float, fit: string, colors: array<int, string>, sizes: array<int, string>}>
     */
    protected function products(): array
    {
        $styles = [
            ['name' => 'Oxford Button-Down Shirt', 'category' => 'Shirts', 'audience' => 'Men', 'material' => '100% cotton oxford', 'base_price' => 34.00, 'fit' => 'Regular fit', 'colors' => ['White', 'Sky Blue'], 'sizes' => ['S', 'M']],
            ['name' => 'Washed Linen Camp Shirt', 'category' => 'Shirts', 'audience' => 'Unisex', 'material' => 'Garment-washed linen', 'base_price' => 39.00, 'fit' => 'Relaxed fit', 'colors' => ['Natural', 'Sage'], 'sizes' => ['S', 'M']],
            ['name' => 'Premium Pique Polo', 'category' => 'Shirts', 'audience' => 'Unisex', 'material' => 'Pique cotton', 'base_price' => 29.00, 'fit' => 'Classic fit', 'colors' => ['Navy', 'Heather Grey'], 'sizes' => ['M', 'L']],
            ['name' => 'Stretch Chino Trouser', 'category' => 'Trousers', 'audience' => 'Men', 'material' => 'Cotton twill with elastane', 'base_price' => 44.00, 'fit' => 'Slim straight fit', 'colors' => ['Khaki', 'Olive'], 'sizes' => ['30', '32']],
            ['name' => 'High-Rise Tailored Trouser', 'category' => 'Trousers', 'audience' => 'Women', 'material' => 'Viscose blend suiting', 'base_price' => 52.00, 'fit' => 'Tailored wide-leg fit', 'colors' => ['Black', 'Taupe'], 'sizes' => ['S', 'M']],
            ['name' => 'Tiered Viscose Maxi Dress', 'category' => 'Dresses', 'audience' => 'Women', 'material' => 'Printed viscose challis', 'base_price' => 58.00, 'fit' => 'Flowing fit', 'colors' => ['Floral Navy', 'Terracotta'], 'sizes' => ['S', 'M']],
            ['name' => 'Crepe Wrap Midi Dress', 'category' => 'Dresses', 'audience' => 'Women', 'material' => 'Soft crepe', 'base_price' => 54.00, 'fit' => 'Adjustable wrap fit', 'colors' => ['Emerald', 'Black'], 'sizes' => ['S', 'M']],
            ['name' => 'Classic Denim Trucker Jacket', 'category' => 'Jackets', 'audience' => 'Unisex', 'material' => 'Midweight denim', 'base_price' => 69.00, 'fit' => 'Boxy fit', 'colors' => ['Indigo', 'Washed Black'], 'sizes' => ['M', 'L']],
            ['name' => 'Recycled Nylon Bomber Jacket', 'category' => 'Jackets', 'audience' => 'Men', 'material' => 'Recycled nylon shell', 'base_price' => 76.00, 'fit' => 'Ribbed hem fit', 'colors' => ['Black', 'Army Green'], 'sizes' => ['M', 'L']],
            ['name' => 'Seamless Performance Legging', 'category' => 'Activewear', 'audience' => 'Women', 'material' => 'Stretch jersey', 'base_price' => 38.00, 'fit' => 'High-compression fit', 'colors' => ['Charcoal', 'Plum'], 'sizes' => ['S', 'M']],
            ['name' => 'Quick-Dry Training Short', 'category' => 'Activewear', 'audience' => 'Unisex', 'material' => 'Quick-dry polyester', 'base_price' => 32.00, 'fit' => 'Athletic fit', 'colors' => ['Black', 'Cobalt'], 'sizes' => ['M', 'L']],
            ['name' => 'Kids Everyday Zip Hoodie', 'category' => 'Kids Clothing', 'audience' => 'Kids', 'material' => 'Cotton fleece', 'base_price' => 31.00, 'fit' => 'Easy kids fit', 'colors' => ['Red', 'Navy'], 'sizes' => ['4Y', '8Y']],
            ['name' => 'School Uniform Poplin Shirt', 'category' => 'Uniforms', 'audience' => 'Kids', 'material' => 'Cotton poplin', 'base_price' => 24.00, 'fit' => 'School fit', 'colors' => ['White', 'Light Blue'], 'sizes' => ['6Y', '10Y']],
            ['name' => 'Industrial Workwear Coverall', 'category' => 'Uniforms', 'audience' => 'Unisex', 'material' => 'Durable cotton twill', 'base_price' => 64.00, 'fit' => 'Utility fit', 'colors' => ['Navy', 'Graphite'], 'sizes' => ['M', 'L']],
            ['name' => 'Brushed Fleece Pullover Hoodie', 'category' => 'Hoodies & Sweaters', 'audience' => 'Unisex', 'material' => 'Brushed fleece', 'base_price' => 48.00, 'fit' => 'Relaxed fit', 'colors' => ['Oatmeal', 'Black'], 'sizes' => ['M', 'L']],
            ['name' => 'Cable Knit Cotton Sweater', 'category' => 'Hoodies & Sweaters', 'audience' => 'Women', 'material' => 'Cotton knit', 'base_price' => 57.00, 'fit' => 'Soft relaxed fit', 'colors' => ['Cream', 'Dusty Rose'], 'sizes' => ['S', 'M']],
            ['name' => 'Water-Repellent Trench Coat', 'category' => 'Coats', 'audience' => 'Women', 'material' => 'Cotton gabardine', 'base_price' => 98.00, 'fit' => 'Belted fit', 'colors' => ['Stone', 'Camel'], 'sizes' => ['S', 'M']],
            ['name' => 'Double-Face Wool Blend Coat', 'category' => 'Coats', 'audience' => 'Men', 'material' => 'Wool blend', 'base_price' => 112.00, 'fit' => 'Tailored outerwear fit', 'colors' => ['Charcoal', 'Camel'], 'sizes' => ['M', 'L']],
            ['name' => 'Pleated Cotton Voile Blouse', 'category' => 'Blouses', 'audience' => 'Women', 'material' => 'Cotton voile', 'base_price' => 42.00, 'fit' => 'Soft drape fit', 'colors' => ['Ivory', 'Powder Blue'], 'sizes' => ['S', 'M']],
            ['name' => 'Ripstop Cargo Pant', 'category' => 'Trousers', 'audience' => 'Teen', 'material' => 'Ripstop cotton', 'base_price' => 49.00, 'fit' => 'Relaxed cargo fit', 'colors' => ['Olive', 'Black'], 'sizes' => ['XS', 'S']],
        ];
        $collections = [
            ['name' => 'Essential', 'price_offset' => 0.00],
            ['name' => 'Heritage', 'price_offset' => 4.00],
            ['name' => 'Urban', 'price_offset' => 7.50],
            ['name' => 'Studio', 'price_offset' => 10.00],
            ['name' => 'Premium', 'price_offset' => 14.00],
        ];

        return collect($collections)->flatMap(fn (array $collection) => collect($styles)->map(fn (array $style): array => array_merge($style, [
            'name' => $collection['name'].' '.$style['name'],
            'base_price' => $style['base_price'] + $collection['price_offset'],
        ])))->values()->all();
    }

    /**
     * @param  array{name: string, category: string, audience: string, material: string, base_price: float, fit: string, colors: array<int, string>, sizes: array<int, string>}  $definition
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
                'description' => "{$definition['name']} is a production-ready {$definition['fit']} garment made from {$definition['material']}. It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.",
                'care_information' => $definition['category'] === 'Coats'
                    ? 'Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.'
                    : 'Machine wash cold with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.',
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

        $sizes = $definition['sizes'];
        $colors = $definition['colors'];
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
                        'attributes' => ['size' => $size, 'color' => $color, 'material' => $definition['material'], 'fit' => $definition['fit']],
                        'price' => $price + ($sizeIndex * 2),
                        'compare_at_price' => $price + 12 + ($sizeIndex * 2),
                        'stock_quantity' => 12 + (($index + $sizeIndex + $colorIndex) % 29),
                        'weight_kg' => $this->weightForCategory($definition['category']),
                        'package_dimensions' => $this->packageDimensionsForCategory($definition['category']),
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

    protected function weightForCategory(string $category): float
    {
        return match ($category) {
            'Coats' => 1.250,
            'Jackets', 'Uniforms' => 0.850,
            'Hoodies & Sweaters' => 0.700,
            'Dresses', 'Trousers' => 0.520,
            'Kids Clothing' => 0.300,
            default => 0.420,
        };
    }

    /** @return array{length_cm: int, width_cm: int, height_cm: int} */
    protected function packageDimensionsForCategory(string $category): array
    {
        return match ($category) {
            'Coats' => ['length_cm' => 55, 'width_cm' => 42, 'height_cm' => 12],
            'Jackets', 'Uniforms' => ['length_cm' => 48, 'width_cm' => 36, 'height_cm' => 10],
            'Hoodies & Sweaters' => ['length_cm' => 42, 'width_cm' => 32, 'height_cm' => 9],
            default => ['length_cm' => 35, 'width_cm' => 28, 'height_cm' => 6],
        };
    }
}
