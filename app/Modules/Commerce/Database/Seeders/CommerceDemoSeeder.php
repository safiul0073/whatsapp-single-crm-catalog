<?php

namespace App\Modules\Commerce\Database\Seeders;

use App\Modules\Commerce\Models\Audience;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\CatalogItemSync;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductOption;
use App\Modules\Commerce\Models\ProductTierPrice;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\VariantPreset;
use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CommerceDemoSeeder extends Seeder
{
    public const SIZES = ['S', 'M', 'L', 'XL', 'XXL', '3XL'];

    public const VIEWS = ['front', 'back', 'side', 'detail', 'lifestyle'];

    public const COLORS = [
        'black' => ['name' => 'Black', 'hex_code' => '#171717', 'color_family' => 'Black'],
        'white' => ['name' => 'White', 'hex_code' => '#F5F5F5', 'color_family' => 'White'],
        'navy' => ['name' => 'Navy', 'hex_code' => '#182C4B', 'color_family' => 'Blue'],
        'grey' => ['name' => 'Grey', 'hex_code' => '#808080', 'color_family' => 'Grey'],
        'red' => ['name' => 'Red', 'hex_code' => '#B5222D', 'color_family' => 'Red'],
        'olive' => ['name' => 'Olive', 'hex_code' => '#687047', 'color_family' => 'Green'],
        'beige' => ['name' => 'Beige', 'hex_code' => '#D4C3A3', 'color_family' => 'Beige'],
    ];

    public function run(): void
    {
        $workspaceId = config('commerce-demo.workspace_id');
        if (! $workspaceId) {
            $this->command?->warn('Commerce demo skipped. Set COMMERCE_DEMO_WORKSPACE_ID to an existing workspace ID.');

            return;
        }

        $workspace = Workspace::query()->with('owner')->findOrFail($workspaceId);
        if (! $workspace->owner) {
            throw new RuntimeException('The demo workspace must have an owner.');
        }

        $definitions = $this->garmentProducts();
        $this->validateAssets($definitions);

        $workspace->getConnection()->transaction(function () use ($workspace, $definitions): void {
            foreach (self::SIZES as $size) {
                VariantPreset::query()->firstOrCreate(
                    ['workspace_id' => $workspace->id, 'name' => $size],
                    ['sku_suffix' => $size, 'price_delta' => 0, 'type' => 'size', 'values' => [$size], 'is_active' => true]
                );
            }

            foreach ($definitions as $index => $definition) {
                $this->seedGarmentProduct($workspace, $index + 1, $definition);
            }
        });

        $this->command?->info('Seeded 5 AI demo products, 210 variants and 175 color-specific images. No Meta sync was triggered.');
    }

    protected function assetPath(string $key, string $color, string $view): string
    {
        return __DIR__."/assets/{$key}/{$color}/{$view}.webp";
    }

    protected function validateAssets(array $definitions): void
    {
        foreach ($definitions as $definition) {
            foreach (array_keys(self::COLORS) as $color) {
                foreach (self::VIEWS as $view) {
                    $path = $this->assetPath($definition['key'], $color, $view);
                    if (! is_file($path) || ! is_readable($path)) {
                        throw new RuntimeException("Missing demo image: {$path}. Deploy all demo assets before seeding.");
                    }
                    $image = getimagesize($path);
                    if (! $image || $image[2] !== IMAGETYPE_WEBP || min($image[0], $image[1]) < 1000) {
                        throw new RuntimeException("Invalid demo image: {$path}. Expected a WebP image of at least 1000 pixels per side.");
                    }
                }
            }
        }
    }

    protected function garmentProducts(): array
    {
        $styles = [
            [
                'name' => '180 GSM Heavyweight Combed Cotton Crewneck T-Shirt',
                'category' => 'T-Shirts',
                'category_slug' => 'men-tops-t-shirts',
                'audience' => 'Unisex',
                'base_price' => 9.00,
                'weight_kg' => 0.180,
                'fabric_gsm' => '180 GSM',
                'material' => '100% Combed Compact Cotton',
                'fit' => 'Standard classic fit',
            ],
            [
                'name' => '240 GSM Premium French Terry Oversized Tee',
                'category' => 'T-Shirts',
                'category_slug' => 'men-tops-t-shirts',
                'audience' => 'Unisex',
                'base_price' => 13.50,
                'weight_kg' => 0.240,
                'fabric_gsm' => '240 GSM French Terry',
                'material' => '100% Bio-Washed Ring-Spun Cotton',
                'fit' => 'Drop-shoulder boxy fit',
            ],
            [
                'name' => '320 GSM Heavyweight Brushed Fleece Pullover Hoodie',
                'category' => 'Hoodies & Sweaters',
                'category_slug' => 'men-tops-hoodies-sweatshirts',
                'audience' => 'Unisex',
                'base_price' => 24.00,
                'weight_kg' => 0.550,
                'fabric_gsm' => '320 GSM Heavy Fleece',
                'material' => '80% Cotton / 20% Poly Anti-Pill Fleece',
                'fit' => 'Relaxed streetwear fit',
            ],
            [
                'name' => '220 GSM Long-Staple Pique Cotton Polo Shirt',
                'category' => 'Polo Shirts',
                'category_slug' => 'men-tops-polos',
                'audience' => 'Men',
                'base_price' => 14.50,
                'weight_kg' => 0.220,
                'fabric_gsm' => '220 GSM Pique',
                'material' => '100% Ring-Spun Cotton Pique',
                'fit' => 'Tailored modern fit',
            ],
            [
                'name' => '12 oz Ring-Spun Stretch Denim Jeans',
                'legacy_name' => '12 oz Ring-Spun Stretch Raw Indigo Denim Jeans',
                'category' => 'Denim & Jeans',
                'category_slug' => 'men-bottoms-jeans',
                'audience' => 'Men',
                'base_price' => 28.00,
                'weight_kg' => 0.650,
                'fabric_gsm' => '12 oz (400 GSM) Denim',
                'material' => '98% Cotton / 2% Spandex Denim',
                'fit' => 'Slim straight 5-pocket fit',
            ],
        ];

        $keys = ['crewneck', 'oversized-tee', 'hoodie', 'polo', 'jeans'];

        return collect($styles)->map(function (array $style, int $index) use ($keys): array {
            $style['key'] = $keys[$index];
            $style['slug'] = 'demo-'.Str::slug('Essential '.($style['legacy_name'] ?? $style['name']));
            $style['name'] = 'Essential '.$style['name'];

            return $style;
        })->all();
    }

    protected function seedGarmentProduct(Workspace $workspace, int $number, array $definition): void
    {
        $workspaceId = $workspace->id;
        $brand = Brand::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => 'demo-loom-studio'],
            ['name' => 'Demo Loom Studio', 'is_active' => true]
        );
        $audience = Audience::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => Str::slug($definition['audience'])],
            ['name' => $definition['audience'], 'is_active' => true]
        );
        $parent = Category::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => 'men'],
            ['name' => 'Men', 'is_active' => true]
        );
        $group = $definition['key'] === 'jeans' ? 'Bottoms' : 'Tops';
        $groupCategory = Category::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => 'men-'.Str::slug($group)],
            ['name' => $group, 'parent_id' => $parent->id, 'is_active' => true]
        );
        $category = Category::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => $definition['category_slug']],
            ['name' => $definition['category'], 'parent_id' => $groupCategory->id, 'is_active' => true]
        );
        $price = $definition['base_price'];
        $product = Product::query()->updateOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => $definition['slug']],
            [
                'name' => $definition['name'],
                'sku' => sprintf('DEMO-%03d', $number),
                'brand_id' => $brand->id,
                'brand' => $brand->name,
                'category_id' => $category->id,
                'audience_id' => $audience->id,
                'audience' => $definition['audience'],
                'gender' => $definition['audience'] === 'Men' ? 'male' : 'unisex',
                'short_description' => $definition['fit'].' in '.$definition['material'].'. AI-generated demo product.',
                'description' => $definition['name'].' features '.$definition['fit'].' and '.$definition['material'].' ('.$definition['fabric_gsm'].'). Available in six sizes and seven colors. Fictional demo merchandise with AI-generated imagery for storefront and catalog testing.',
                'care_information' => 'Machine wash cold with similar colors. Do not bleach. Line dry. Iron on low if needed.',
                'features' => [$definition['material'], $definition['fabric_gsm'], $definition['fit'], 'Six sizes: S to 3XL', 'Seven colors', 'AI-generated demo imagery'],
                'feature_highlights' => [
                    ['label' => 'SIX SIZES', 'icon' => 'ph-ruler'],
                    ['label' => 'SEVEN COLORS', 'icon' => 'ph-palette'],
                ],
                'fit' => $definition['fit'],
                'set_includes' => 'One garment',
                'season' => 'All Season',
                'shipping_info' => null,
                'delivery_time' => null,
                'moq' => 1,
                'rating' => 0,
                'reviews_count' => 0,
                'fabric_gsm' => $definition['fabric_gsm'],
                'material' => $definition['material'],
                'single_piece_price' => $price,
                'wholesale_price' => round($price * 0.72, 2),
                'selling_mode' => 'both',
                'ws_enabled' => true,
                'ws_min_sizes' => 3,
                'ws_color_moq' => 1,
                'ws_main_moq' => 10,
                'ws_ratio_multiplier' => 1,
                'default_unit_weight_kg' => $definition['weight_kg'],
                'default_package_dimensions' => ['length_cm' => 35, 'width_cm' => 28, 'height_cm' => 6],
                'condition' => 'new',
                'country_of_origin' => 'BD',
                'status' => 'active',
                'wizard_step' => 5,
                'published_at' => now(),
            ]
        );

        $mediaIds = $colorIds = $variantIds = $ratios = [];
        foreach (self::COLORS as $colorKey => $colorData) {
            $color = ProductColor::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'product_id' => $product->id, 'name' => $colorData['name']],
                [...$colorData, 'position' => count($colorIds)]
            );
            $colorIds[] = $color->id;
            $primary = null;
            foreach (self::VIEWS as $position => $view) {
                $source = $this->assetPath($definition['key'], $colorKey, $view);
                $path = 'commerce-demo/v1/'.$definition['key']."/{$colorKey}/{$view}.webp";
                if (! Storage::disk('public')->put($path, file_get_contents($source))) {
                    throw new RuntimeException("Could not store demo image: {$path}");
                }
                $filename = 'commerce-demo-'.$definition['key']."-{$colorKey}-{$view}.webp";
                $media = Media::query()->updateOrCreate(
                    ['uploaded_by' => $workspace->owner_id, 'disk' => 'public', 'path' => $path],
                    [
                        'name' => $definition['name']." — {$colorData['name']} {$view}",
                        'file_name' => $filename,
                        'original_name' => $filename,
                        'mime_type' => 'image/webp',
                        'extension' => 'webp',
                        'type' => 'image',
                        'size' => filesize($source),
                        'alt' => "AI demo: {$definition['name']} in {$colorData['name']}, {$view} view",
                    ]
                );
                $mediaIds[] = $media->id;
                $isPrimary = $colorKey === 'black' && $view === 'front';
                ProductMedia::query()->updateOrCreate(
                    ['product_id' => $product->id, 'media_id' => $media->id],
                    ['workspace_id' => $workspaceId, 'color_id' => $color->id, 'media_type' => 'image', 'role' => $isPrimary ? 'primary' : 'gallery', 'alt_text' => $media->alt, 'position' => count($mediaIds) - 1, 'is_primary' => $isPrimary]
                );
                if ($view === 'front') {
                    $primary = $media;
                    $color->update(['swatch_media_id' => $media->id]);
                }
                if ($isPrimary) {
                    $product->update(['primary_media_id' => $media->id]);
                }
            }

            $ratios[$color->id] = array_fill_keys(self::SIZES, 1);
            foreach (self::SIZES as $sizeIndex => $size) {
                $sku = sprintf('DEMO-%03d-%s-%s', $number, $size, strtoupper($colorKey));
                $variant = ProductVariant::query()->firstOrNew(['workspace_id' => $workspaceId, 'sku' => $sku]);
                if ($variant->exists && $variant->product_id !== $product->id) {
                    throw new RuntimeException("Demo SKU collision: {$sku}");
                }
                $variant->fill([
                    'product_id' => $product->id,
                    'color_id' => $color->id,
                    'size' => $size,
                    'media_id' => $primary->id,
                    'meta_retailer_id' => $variant->meta_retailer_id ?: strtolower($sku),
                    'attributes' => ['size' => $size, 'color' => $colorData['name'], 'material' => $definition['material'], 'gender' => $definition['audience'] === 'Men' ? 'male' : 'unisex', 'age_group' => 'adult', 'pattern' => 'solid'],
                    'price' => $price + ($sizeIndex >= 4 ? 2 : 0),
                    'compare_at_price' => null,
                    'stock_quantity' => 20 + $sizeIndex,
                    'weight_kg' => $definition['weight_kg'],
                    'package_dimensions' => ['length_cm' => 35, 'width_cm' => 28, 'height_cm' => 6],
                    'status' => 'active',
                ])->save();
                $variantIds[] = $variant->id;
            }
        }

        $obsolete = $product->variants()->whereNotIn('id', $variantIds)->get();
        foreach ($obsolete as $variant) {
            if (! str_starts_with($variant->sku, sprintf('DEMO-%03d-', $number)) || $variant->orderItems()->exists() || CatalogItemSync::query()->where('variant_id', $variant->id)->exists()) {
                throw new RuntimeException('Existing non-demo, ordered or synced variants require manual reconciliation before reseeding product '.$product->id.'.');
            }
            $variant->delete();
        }
        $product->gallery()->whereNotIn('media_id', $mediaIds)
            ->whereHas('media', fn ($query) => $query->where('file_name', 'like', 'commerce-demo-%'))
            ->delete();
        $product->colors()->whereNotIn('id', $colorIds)
            ->whereDoesntHave('variants')->whereDoesntHave('gallery')->delete();
        $product->update(['ws_size_ratios' => $ratios]);
        $this->option($workspaceId, $product->id, 'Size', 'size', 0, self::SIZES);
        $this->option($workspaceId, $product->id, 'Color', 'color', 1, array_column(self::COLORS, 'name'));

        foreach ([[10, 49, .88], [50, 99, .78], [100, 499, .68], [500, null, .58]] as [$min, $max, $factor]) {
            ProductTierPrice::query()->updateOrCreate(
                ['workspace_id' => $workspaceId, 'product_id' => $product->id, 'min_quantity' => $min],
                ['max_quantity' => $max, 'unit_price' => round($price * $factor, 2), 'discount_percentage' => round((1 - $factor) * 100)]
            );
        }
    }

    protected function option(int $workspaceId, int $productId, string $name, string $code, int $position, array $values): void
    {
        $option = ProductOption::query()->updateOrCreate(
            ['product_id' => $productId, 'code' => $code],
            ['workspace_id' => $workspaceId, 'name' => $name, 'position' => $position]
        );
        $option->values()->whereNotIn('value', $values)->delete();
        foreach ($values as $index => $value) {
            $option->values()->updateOrCreate(['value' => $value], ['workspace_id' => $workspaceId, 'position' => $index]);
        }
    }
}
