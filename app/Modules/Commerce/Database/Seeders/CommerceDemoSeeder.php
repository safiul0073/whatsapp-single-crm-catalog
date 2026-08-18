<?php

namespace App\Modules\Commerce\Database\Seeders;

use App\Models\User;
use App\Modules\Commerce\Models\Audience;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductOption;
use App\Modules\Commerce\Models\ProductTierPrice;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\VariantPreset;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CommerceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();
        if (! $user) {
            $user = User::query()->create([
                'name' => 'Garment Exporter Store',
                'email' => 'user@mail.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $workspace = Workspace::query()->with('owner')->orderBy('id')->first();
        if (! $workspace) {
            $workspace = app(WorkspaceResolver::class)->current($user);
        }

        if (! $workspace) {
            $workspace = Workspace::query()->create([
                'owner_id' => $user->id,
                'name' => 'Garment Exporter Store',
                'slug' => 'garment-exporter-store',
                'status' => 'active',
                'settings' => ['commerce' => ['shop_enabled' => true, 'currency' => 'USD', 'storefront_title' => 'Garment Direct Export Store']],
            ]);
        }

        DB::transaction(function () use ($workspace): void {
            $brands = $this->brands($workspace->id);
            $audiences = $this->audiences($workspace->id);
            $categories = $this->categories($workspace->id);
            $this->variantPresets($workspace->id);
            $media = $this->media((int) $workspace->owner_id);

            foreach ($this->garmentProducts() as $index => $definition) {
                $this->seedGarmentProduct($workspace->id, $index, $definition, $brands, $audiences, $categories, $media);
            }
        });
    }

    /** @return array<string, Brand> */
    protected function brands(int $workspaceId): array
    {
        return collect([
            'Dhaka Loom Studio',
            'Bengal Threadworks',
            'River & Reed Apparel',
            'Northstar Garments',
            'Urban Weave Co.',
            'Cotton House BD',
            'Apex Knitwear Export',
            'Summit Activewear',
        ])->mapWithKeys(function (string $name) use ($workspaceId): array {
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
        return collect(['Unisex', 'Men', 'Women', 'Kids', 'Teen'])
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
        return collect(['Shirts', 'T-Shirts', 'Hoodies & Sweaters', 'Polo Shirts', 'Denim & Jeans', 'Activewear', 'Trousers', 'Coats', 'Uniforms', 'Kids Clothing', 'Dresses', 'Blouses', 'Jackets'])
            ->mapWithKeys(function (string $name) use ($workspaceId): array {
                $category = Category::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
                    ['name' => $name, 'parent_id' => null, 'is_active' => true]
                );

                return [$name => $category];
            })->all();
    }

    protected function variantPresets(int $workspaceId): void
    {
        $presets = [
            ['name' => 'Small', 'sku_suffix' => 'S', 'price_delta' => 0.00, 'type' => 'size', 'values' => ['S']],
            ['name' => 'Medium', 'sku_suffix' => 'M', 'price_delta' => 0.00, 'type' => 'size', 'values' => ['M']],
            ['name' => 'Large', 'sku_suffix' => 'L', 'price_delta' => 0.00, 'type' => 'size', 'values' => ['L']],
            ['name' => 'XL', 'sku_suffix' => 'XL', 'price_delta' => 0.00, 'type' => 'size', 'values' => ['XL']],
            ['name' => 'XXL', 'sku_suffix' => 'XXL', 'price_delta' => 1.50, 'type' => 'size', 'values' => ['XXL']],
            ['name' => '3XL', 'sku_suffix' => '3XL', 'price_delta' => 2.00, 'type' => 'size', 'values' => ['3XL']],
            ['name' => 'Adult Standard (S–XXL)', 'sku_suffix' => 'STD', 'price_delta' => 0.00, 'type' => 'size', 'values' => ['S', 'M', 'L', 'XL', 'XXL']],
        ];

        foreach ($presets as $preset) {
            VariantPreset::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'name' => $preset['name']],
                [
                    'sku_suffix' => $preset['sku_suffix'],
                    'price_delta' => $preset['price_delta'],
                    'type' => $preset['type'],
                    'values' => $preset['values'],
                    'is_active' => true,
                ]
            );
        }
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
     * Generate 100 realistic garments (20 real styles across 5 collections) with real fabrics, GSM, weights & wholesale volume tiers.
     */
    protected function garmentProducts(): array
    {
        $styles = [
            [
                'name' => '180 GSM Heavyweight Combed Cotton Crewneck T-Shirt',
                'category' => 'T-Shirts',
                'audience' => 'Unisex',
                'base_price' => 9.00,
                'weight_kg' => 0.180,
                'fabric_gsm' => '180 GSM',
                'material' => '100% Combed Compact Cotton',
                'fit' => 'Standard classic fit',
                'colors' => [
                    ['name' => 'Royal Blue', 'hex_code' => '#1E3A8A', 'color_family' => 'Blue'],
                    ['name' => 'Jet Black', 'hex_code' => '#111827', 'color_family' => 'Black'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => '240 GSM Premium French Terry Oversized Tee',
                'category' => 'T-Shirts',
                'audience' => 'Unisex',
                'base_price' => 13.50,
                'weight_kg' => 0.240,
                'fabric_gsm' => '240 GSM French Terry',
                'material' => '100% Bio-Washed Ring-Spun Cotton',
                'fit' => 'Drop-shoulder boxy fit',
                'colors' => [
                    ['name' => 'Washed Charcoal', 'hex_code' => '#374151', 'color_family' => 'Grey'],
                    ['name' => 'Warm Cream', 'hex_code' => '#F3F4F6', 'color_family' => 'White'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => '320 GSM Heavyweight Brushed Fleece Pullover Hoodie',
                'category' => 'Hoodies & Sweaters',
                'audience' => 'Unisex',
                'base_price' => 24.00,
                'weight_kg' => 0.550,
                'fabric_gsm' => '320 GSM Heavy Fleece',
                'material' => '80% Cotton / 20% Poly Anti-Pill Fleece',
                'fit' => 'Relaxed streetwear fit',
                'colors' => [
                    ['name' => 'Pitch Black', 'hex_code' => '#111827', 'color_family' => 'Black'],
                    ['name' => 'Athletic Heather', 'hex_code' => '#D1D5DB', 'color_family' => 'Grey'],
                ],
                'sizes' => ['M', 'L'],
            ],
            [
                'name' => '220 GSM Long-Staple Pique Cotton Polo Shirt',
                'category' => 'Polo Shirts',
                'audience' => 'Men',
                'base_price' => 14.50,
                'weight_kg' => 0.220,
                'fabric_gsm' => '220 GSM Pique',
                'material' => '100% Ring-Spun Cotton Pique',
                'fit' => 'Tailored modern fit',
                'colors' => [
                    ['name' => 'Navy Blue', 'hex_code' => '#1E3A8A', 'color_family' => 'Blue'],
                    ['name' => 'Bright White', 'hex_code' => '#FFFFFF', 'color_family' => 'White'],
                ],
                'sizes' => ['M', 'L'],
            ],
            [
                'name' => '12 oz Ring-Spun Stretch Raw Indigo Denim Jeans',
                'category' => 'Denim & Jeans',
                'audience' => 'Men',
                'base_price' => 28.00,
                'weight_kg' => 0.650,
                'fabric_gsm' => '12 oz (400 GSM) Denim',
                'material' => '98% Cotton / 2% Spandex Denim',
                'fit' => 'Slim straight 5-pocket fit',
                'colors' => [
                    ['name' => 'Raw Indigo', 'hex_code' => '#1E293B', 'color_family' => 'Blue'],
                    ['name' => 'Medium Stone Wash', 'hex_code' => '#3B82F6', 'color_family' => 'Blue'],
                ],
                'sizes' => ['30', '32'],
            ],
            [
                'name' => '150 GSM Breathable Quick-Dry Dry-Fit Athletic Jersey',
                'category' => 'Activewear',
                'audience' => 'Unisex',
                'base_price' => 8.50,
                'weight_kg' => 0.140,
                'fabric_gsm' => '150 GSM Dry-Fit',
                'material' => '100% Micro-Polyester Moisture Mesh',
                'fit' => 'Athletic ergonomic fit',
                'colors' => [
                    ['name' => 'Volt Lime', 'hex_code' => '#84CC16', 'color_family' => 'Green'],
                    ['name' => 'Stealth Black', 'hex_code' => '#111827', 'color_family' => 'Black'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => 'High-Waist Seamless 4-Way Stretch Compression Leggings',
                'category' => 'Activewear',
                'audience' => 'Women',
                'base_price' => 16.00,
                'weight_kg' => 0.210,
                'fabric_gsm' => '260 GSM Compression Knit',
                'material' => '75% Nylon / 25% Spandex Matte Interlock',
                'fit' => 'High-compression tummy control fit',
                'colors' => [
                    ['name' => 'Obsidian Black', 'hex_code' => '#0F172A', 'color_family' => 'Black'],
                    ['name' => 'Olive Green', 'hex_code' => '#3F6212', 'color_family' => 'Green'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => 'Heavy-Duty 240 GSM Industrial Twill Utility Coverall',
                'category' => 'Uniforms',
                'audience' => 'Unisex',
                'base_price' => 34.00,
                'weight_kg' => 0.750,
                'fabric_gsm' => '240 GSM Poly-Cotton Twill',
                'material' => '65% Polyester / 35% Cotton Twill',
                'fit' => 'Bi-swing utility work fit',
                'colors' => [
                    ['name' => 'Industrial Navy', 'hex_code' => '#1E3A8A', 'color_family' => 'Blue'],
                    ['name' => 'Graphite Grey', 'hex_code' => '#4B5563', 'color_family' => 'Grey'],
                ],
                'sizes' => ['M', 'L'],
            ],
            [
                'name' => 'Double-Face Wool Blend Coat',
                'category' => 'Coats',
                'audience' => 'Men',
                'base_price' => 112.00,
                'weight_kg' => 1.250,
                'fabric_gsm' => '650 GSM Heavy Wool',
                'material' => '70% Wool / 30% Polyamide Blend',
                'fit' => 'Tailored outerwear overcoat fit',
                'colors' => [
                    ['name' => 'Charcoal Heather', 'hex_code' => '#374151', 'color_family' => 'Grey'],
                    ['name' => 'Camel Tan', 'hex_code' => '#D97706', 'color_family' => 'Earth'],
                ],
                'sizes' => ['M', 'L'],
            ],
            [
                'name' => '160 GSM Bio-Washed Soft-Touch Kids Crewneck T-Shirt',
                'category' => 'Kids Clothing',
                'audience' => 'Kids',
                'base_price' => 6.50,
                'weight_kg' => 0.110,
                'fabric_gsm' => '160 GSM Single Jersey',
                'material' => '100% Bio-Washed Combed Cotton',
                'fit' => 'Gentle kids everyday fit',
                'colors' => [
                    ['name' => 'Bright Yellow', 'hex_code' => '#EAB308', 'color_family' => 'Yellow'],
                    ['name' => 'Sky Blue', 'hex_code' => '#38BDF8', 'color_family' => 'Blue'],
                ],
                'sizes' => ['4Y', '8Y'],
            ],
            [
                'name' => '280 GSM Full-Zip French Terry Track Bomber Jacket',
                'category' => 'Jackets',
                'audience' => 'Men',
                'base_price' => 28.00,
                'weight_kg' => 0.480,
                'fabric_gsm' => '280 GSM French Terry',
                'material' => '100% Combed Cotton Terry Loop',
                'fit' => 'Athletic baseball collar fit',
                'colors' => [
                    ['name' => 'Jet Black', 'hex_code' => '#111827', 'color_family' => 'Black'],
                    ['name' => 'Olive Drab', 'hex_code' => '#365314', 'color_family' => 'Green'],
                ],
                'sizes' => ['M', 'L'],
            ],
            [
                'name' => '200 GSM Cotton Twill 6-Pocket Cargo Shorts',
                'category' => 'Trousers',
                'audience' => 'Men',
                'base_price' => 17.00,
                'weight_kg' => 0.320,
                'fabric_gsm' => '200 GSM Cotton Twill',
                'material' => '100% Combed Cotton Twill',
                'fit' => 'Relaxed cargo fit',
                'colors' => [
                    ['name' => 'Desert Khaki', 'hex_code' => '#D97706', 'color_family' => 'Earth'],
                    ['name' => 'Tactical Olive', 'hex_code' => '#3F6212', 'color_family' => 'Green'],
                ],
                'sizes' => ['30', '32'],
            ],
            [
                'name' => 'Oxford Button-Down Long Sleeve Shirt',
                'category' => 'Shirts',
                'audience' => 'Men',
                'base_price' => 22.00,
                'weight_kg' => 0.280,
                'fabric_gsm' => '140 GSM Oxford Cloth',
                'material' => '100% Compact Oxford Cotton',
                'fit' => 'Classic button-down collar fit',
                'colors' => [
                    ['name' => 'Classic White', 'hex_code' => '#FFFFFF', 'color_family' => 'White'],
                    ['name' => 'Sky Oxford Blue', 'hex_code' => '#60A5FA', 'color_family' => 'Blue'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => 'Washed Linen Camp Collar Summer Shirt',
                'category' => 'Shirts',
                'audience' => 'Unisex',
                'base_price' => 26.00,
                'weight_kg' => 0.210,
                'fabric_gsm' => '160 GSM Pure Linen',
                'material' => '100% Garment-Washed French Linen',
                'fit' => 'Relaxed resort cuban fit',
                'colors' => [
                    ['name' => 'Natural Flax', 'hex_code' => '#E5E7EB', 'color_family' => 'White'],
                    ['name' => 'Sage Leaf', 'hex_code' => '#84CC16', 'color_family' => 'Green'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => 'Stretch Cotton Slim-Fit Casual Chino Trouser',
                'category' => 'Trousers',
                'audience' => 'Men',
                'base_price' => 24.00,
                'weight_kg' => 0.420,
                'fabric_gsm' => '240 GSM Stretch Twill',
                'material' => '97% Cotton / 3% Spandex Twill',
                'fit' => 'Slim tapered stretch fit',
                'colors' => [
                    ['name' => 'British Khaki', 'hex_code' => '#CA8A04', 'color_family' => 'Earth'],
                    ['name' => 'Navy Blue', 'hex_code' => '#1E3A8A', 'color_family' => 'Blue'],
                ],
                'sizes' => ['30', '32'],
            ],
            [
                'name' => 'Water-Repellent Belted Trench Coat',
                'category' => 'Coats',
                'audience' => 'Women',
                'base_price' => 85.00,
                'weight_kg' => 0.950,
                'fabric_gsm' => '280 GSM Cotton Gabardine',
                'material' => '100% Water-Resistant Cotton Gabardine',
                'fit' => 'Double-breasted belted trench fit',
                'colors' => [
                    ['name' => 'Classic Stone', 'hex_code' => '#E5E7EB', 'color_family' => 'Grey'],
                    ['name' => 'Golden Camel', 'hex_code' => '#D97706', 'color_family' => 'Earth'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => 'Pleated Cotton Voile Summer Blouse',
                'category' => 'Blouses',
                'audience' => 'Women',
                'base_price' => 19.00,
                'weight_kg' => 0.160,
                'fabric_gsm' => '90 GSM Cotton Voile',
                'material' => '100% Superfine Cotton Voile',
                'fit' => 'Flowy pleated romantic fit',
                'colors' => [
                    ['name' => 'Soft Ivory', 'hex_code' => '#FEF08A', 'color_family' => 'White'],
                    ['name' => 'Powder Blue', 'hex_code' => '#93C5FD', 'color_family' => 'Blue'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => 'Tiered Viscose Floral Bohemian Maxi Dress',
                'category' => 'Dresses',
                'audience' => 'Women',
                'base_price' => 32.00,
                'weight_kg' => 0.350,
                'fabric_gsm' => '130 GSM Viscose Challis',
                'material' => '100% Eco-Vero Viscose Challis',
                'fit' => 'Tiered bohemian maxi fit',
                'colors' => [
                    ['name' => 'Floral Navy', 'hex_code' => '#1E293B', 'color_family' => 'Blue'],
                    ['name' => 'Terracotta Floral', 'hex_code' => '#C2410C', 'color_family' => 'Red'],
                ],
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => 'Classic 14 oz Heavyweight Denim Trucker Jacket',
                'category' => 'Jackets',
                'audience' => 'Unisex',
                'base_price' => 38.00,
                'weight_kg' => 0.850,
                'fabric_gsm' => '14 oz (470 GSM) Denim',
                'material' => '100% Heavy Cotton Denim',
                'fit' => 'Boxy trucker jacket fit',
                'colors' => [
                    ['name' => 'Vintage Blue', 'hex_code' => '#3B82F6', 'color_family' => 'Blue'],
                    ['name' => 'Washed Black', 'hex_code' => '#18181B', 'color_family' => 'Black'],
                ],
                'sizes' => ['M', 'L'],
            ],
            [
                'name' => 'High-Density Recycled Flight Bomber Jacket',
                'category' => 'Jackets',
                'audience' => 'Men',
                'base_price' => 45.00,
                'weight_kg' => 0.650,
                'fabric_gsm' => '210T Recycled Nylon Twill',
                'material' => '100% Recycled Nylon with Polyfil Padding',
                'fit' => 'Insulated military bomber fit',
                'colors' => [
                    ['name' => 'Gunmetal Black', 'hex_code' => '#111827', 'color_family' => 'Black'],
                    ['name' => 'Sage Army Green', 'hex_code' => '#3F6212', 'color_family' => 'Green'],
                ],
                'sizes' => ['M', 'L'],
            ],
        ];

        $collections = [
            ['name' => 'Essential', 'price_offset' => 0.00],
            ['name' => 'Heritage', 'price_offset' => 4.00],
            ['name' => 'Urban Export', 'price_offset' => 7.50],
            ['name' => 'Studio', 'price_offset' => 10.00],
            ['name' => 'Premium', 'price_offset' => 14.00],
        ];

        return collect($collections)->flatMap(fn (array $collection) => collect($styles)->map(fn (array $style): array => array_merge($style, [
            'name' => $collection['name'].' '.$style['name'],
            'single_piece_price' => $style['base_price'] + $collection['price_offset'],
            'default_unit_weight_kg' => $style['weight_kg'],
        ])))->values()->all();
    }

    /**
     * Seed a single garment product with full color swatches, tier prices, options, variants & media.
     */
    protected function seedGarmentProduct(
        int $workspaceId,
        int $index,
        array $definition,
        array $brands,
        array $audiences,
        array $categories,
        array $media
    ): void {
        $number = $index + 1;
        $brand = array_values($brands)[$index % count($brands)];
        $audience = $audiences[$definition['audience']] ?? array_values($audiences)[0];
        $category = $categories[$definition['category']] ?? array_values($categories)[0];
        $primaryMedia = $media[(($index * 7) % 60) + 1];
        $secondaryMedia = $media[(($index * 7 + 19) % 60) + 1];
        $price = round($definition['single_piece_price'], 2);
        $slug = 'demo-'.Str::slug($definition['name']);

        $product = Product::query()->updateOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => $slug],
            [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'audience_id' => $audience->id,
                'primary_media_id' => $primaryMedia->id,
                'name' => $definition['name'],
                'brand' => $brand->name,
                'short_description' => "Premium {$definition['name']} – Hoodie & Jogger set designed for all-day comfort and a stylish sporty look.",
                'description' => "{$definition['name']} is a production-ready {$definition['fit']} garment made from {$definition['material']} ({$definition['fabric_gsm']}). It is prepared for WhatsApp catalog selling with clear variant data, retail-friendly photography, and reliable stock quantities for wholesale or direct customer orders.",
                'care_information' => $definition['category'] === 'Coats'
                    ? 'Dry clean recommended. Hang after wear. Steam lightly if needed. Do not bleach.'
                    : 'Machine wash warm (40°C) with similar colors. Use mild detergent. Do not bleach. Tumble dry low or line dry. Iron on low when needed.',
                'features' => [
                    '2-Piece Set – Full-Zip Hoodie + Jogger Pants',
                    'Premium Tech Fleece Fabric',
                    'Soft, Comfortable & Warm',
                    'Full-Zip Hoodie with Pockets',
                    'Comfortable Elastic Waist Joggers',
                    'Suitable for Boys & Girls',
                    'Kids to Older Kids Sizes Available',
                    'Multiple Colors Available',
                    'USA True-to-Size Fit',
                    'Retail & Wholesale Available',
                    'Factory Direct Supply',
                    'Worldwide Shipping Available',
                ],
                'feature_highlights' => [
                    ['label' => 'PREMIUM TECH FLEECE', 'icon' => 'ph-t-shirt'],
                    ['label' => 'FULL-ZIP 2-PIECE SET', 'icon' => 'ph-arrows-out-line-vertical'],
                    ['label' => 'KIDS TO OLDER KIDS', 'icon' => 'ph-users-three'],
                    ['label' => 'MULTIPLE COLORS', 'icon' => 'ph-palette'],
                ],
                'fit' => $definition['fit'] ?? 'USA True-to-Size',
                'set_includes' => 'Hoodie + Jogger Pants',
                'gender' => 'Unisex (Boys & Girls)',
                'season' => 'All Season',
                'shipping_info' => 'USA & Canada Shipping',
                'delivery_time' => '6–10 Working Days Delivery',
                'moq' => 40,
                'rating' => 5.00,
                'reviews_count' => 128,
                'fabric_gsm' => $definition['fabric_gsm'],
                'material' => $definition['material'],
                'single_piece_price' => $price,
                'wholesale_price' => round($price * 0.72, 2),
                'default_unit_weight_kg' => $definition['default_unit_weight_kg'],
                'condition' => 'new',
                'audience' => $audience->name,
                'country_of_origin' => 'BD',
                'status' => 'active',
                'wizard_step' => 5,
                'published_at' => now(),
            ]
        );

        // Build 20 realistic colorways for showcase if this is the first product
        $colorsToSeed = $definition['colors'];
        if ($index === 0) {
            $colorsToSeed = [
                ['name' => 'Light Blue', 'hex_code' => '#60A5FA', 'color_family' => 'Blue'],
                ['name' => 'Green', 'hex_code' => '#10B981', 'color_family' => 'Green'],
                ['name' => 'Olive', 'hex_code' => '#84CC16', 'color_family' => 'Green'],
                ['name' => 'Royal Blue', 'hex_code' => '#1E3A8A', 'color_family' => 'Blue'],
                ['name' => 'Maroon', 'hex_code' => '#991B1B', 'color_family' => 'Red'],
                ['name' => 'Purple', 'hex_code' => '#8B5CF6', 'color_family' => 'Purple'],
                ['name' => 'Steel Blue', 'hex_code' => '#3B82F6', 'color_family' => 'Blue'],
                ['name' => 'Navy', 'hex_code' => '#1E293B', 'color_family' => 'Blue'],
                ['name' => 'Dark Grey', 'hex_code' => '#374151', 'color_family' => 'Grey'],
                ['name' => 'Camel', 'hex_code' => '#D97706', 'color_family' => 'Earth'],
                ['name' => 'Pink', 'hex_code' => '#EC4899', 'color_family' => 'Pink'],
                ['name' => 'Brown', 'hex_code' => '#78350F', 'color_family' => 'Earth'],
                ['name' => 'Army Green', 'hex_code' => '#3F6212', 'color_family' => 'Green'],
                ['name' => 'Coral', 'hex_code' => '#F43F5E', 'color_family' => 'Red'],
                ['name' => 'Mint Green', 'hex_code' => '#34D399', 'color_family' => 'Green'],
                ['name' => 'Yellow', 'hex_code' => '#F59E0B', 'color_family' => 'Yellow'],
                ['name' => 'Red', 'hex_code' => '#EF4444', 'color_family' => 'Red'],
                ['name' => 'Deep Navy', 'hex_code' => '#0F172A', 'color_family' => 'Blue'],
                ['name' => 'Teal', 'hex_code' => '#0D9488', 'color_family' => 'Green'],
            ];
        }

        // Seed Product Media Gallery & Color Swatches
        $colorModels = [];
        $pos = 0;
        foreach ($colorsToSeed as $cPos => $colorData) {
            $colorMedia1 = $media[(($index * 7 + $cPos * 13) % 60) + 1];
            $colorMedia2 = $media[(($index * 7 + $cPos * 13 + 5) % 60) + 1];

            $colorModel = ProductColor::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'product_id' => $product->id, 'name' => $colorData['name']],
                [
                    'hex_code' => $colorData['hex_code'],
                    'color_family' => $colorData['color_family'] ?? null,
                    'swatch_media_id' => $colorMedia1->id,
                    'position' => $cPos,
                ]
            );
            $colorModels[$colorData['name']] = $colorModel;

            // Seed primary photo for this color
            ProductMedia::query()->updateOrCreate(
                ['product_id' => $product->id, 'media_id' => $colorMedia1->id],
                [
                    'workspace_id' => $workspaceId,
                    'color_id' => $colorModel->id,
                    'media_type' => 'image',
                    'role' => $cPos === 0 ? 'primary' : 'gallery',
                    'alt_text' => $definition['name'].' in '.$colorData['name'].' - Front',
                    'position' => $pos++,
                    'is_primary' => $cPos === 0,
                ]
            );

            // Seed secondary angle photo for this color
            ProductMedia::query()->updateOrCreate(
                ['product_id' => $product->id, 'media_id' => $colorMedia2->id],
                [
                    'workspace_id' => $workspaceId,
                    'color_id' => $colorModel->id,
                    'media_type' => 'image',
                    'role' => 'gallery',
                    'alt_text' => $definition['name'].' in '.$colorData['name'].' - Angle',
                    'position' => $pos++,
                    'is_primary' => false,
                ]
            );
        }

        // Seed Wholesale Volume Tier Pricing
        $tiers = [
            ['min_quantity' => 10, 'max_quantity' => 49, 'unit_price' => round($price * 0.88, 2), 'discount_percentage' => 12],
            ['min_quantity' => 50, 'max_quantity' => 99, 'unit_price' => round($price * 0.78, 2), 'discount_percentage' => 22],
            ['min_quantity' => 100, 'max_quantity' => 499, 'unit_price' => round($price * 0.68, 2), 'discount_percentage' => 32],
            ['min_quantity' => 500, 'max_quantity' => null, 'unit_price' => round($price * 0.58, 2), 'discount_percentage' => 42],
        ];

        foreach ($tiers as $tierData) {
            ProductTierPrice::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'product_id' => $product->id, 'min_quantity' => $tierData['min_quantity']],
                [
                    'max_quantity' => $tierData['max_quantity'],
                    'unit_price' => $tierData['unit_price'],
                    'discount_percentage' => $tierData['discount_percentage'],
                ]
            );
        }

        // Seed Product Options (Size & Color)
        $sizes = $definition['sizes'];
        $colorNames = collect($definition['colors'])->pluck('name')->all();

        $sizeOption = $this->option($workspaceId, $product->id, 'Size', 'size', 0, $sizes);
        $colorOption = $this->option($workspaceId, $product->id, 'Color', 'color', 1, $colorNames);

        // Seed Variants with Color ID, dedicated Color Image, and Size
        foreach ($sizes as $sizeIndex => $size) {
            foreach ($definition['colors'] as $colorIndex => $colorData) {
                $colorName = $colorData['name'];
                $colorObj = $colorModels[$colorName] ?? null;
                $suffix = Str::upper(Str::slug($size.'-'.$colorName, '-'));
                $variantMediaId = $colorObj?->swatch_media_id ?? $primaryMedia->id;

                ProductVariant::query()->updateOrCreate(
                    ['workspace_id' => $workspaceId, 'sku' => sprintf('DEMO-%03d-%s', $number, $suffix)],
                    [
                        'product_id' => $product->id,
                        'color_id' => $colorObj?->id,
                        'size' => $size,
                        'media_id' => $variantMediaId,
                        'meta_retailer_id' => sprintf('demo-%03d-%s', $number, Str::lower($suffix)),
                        'attributes' => [
                            'size' => $size,
                            'color' => $colorName,
                            'material' => $definition['material'],
                            'fit' => $definition['fit'],
                            'gsm' => $definition['fabric_gsm'],
                        ],
                        'price' => $price + ($sizeIndex * 2),
                        'compare_at_price' => $price + 12 + ($sizeIndex * 2),
                        'stock_quantity' => 12 + (($index + $sizeIndex + $colorIndex) % 29),
                        'weight_kg' => $definition['default_unit_weight_kg'],
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
