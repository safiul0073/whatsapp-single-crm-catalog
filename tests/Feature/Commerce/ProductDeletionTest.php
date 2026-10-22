<?php

use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductOption;
use App\Modules\Commerce\Models\ProductOptionValue;
use App\Modules\Commerce\Models\ProductTierPrice;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
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

it('deletes a product that has colors, variants, options, and gallery media without foreign key constraint failure', function (): void {
    $product = Product::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Product',
        'slug' => 'test-product',
        'status' => 'published',
    ]);

    $color = ProductColor::query()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $product->id,
        'name' => 'Royal Blue',
        'hex_code' => '#0000FF',
    ]);

    $media = Media::query()->create([
        'name' => 'Sample Image',
        'file_name' => 'sample.jpg',
        'original_name' => 'sample.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'type' => 'image',
        'size' => 1024,
        'disk' => 'public',
        'path' => 'test/sample.jpg',
    ]);

    $productMedia = ProductMedia::query()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $product->id,
        'media_id' => $media->id,
        'color_id' => $color->id,
        'media_type' => 'image',
        'role' => 'gallery',
    ]);

    $variant = ProductVariant::query()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $product->id,
        'color_id' => $color->id,
        'sku' => 'TEST-SKU-1',
        'meta_retailer_id' => 'TEST-RET-1',
        'price' => 25.00,
    ]);

    $tierPrice = ProductTierPrice::query()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $product->id,
        'min_quantity' => 10,
        'unit_price' => 20.00,
    ]);

    $option = ProductOption::query()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $product->id,
        'name' => 'Size',
        'code' => 'size',
    ]);

    $optionValue = ProductOptionValue::query()->create([
        'workspace_id' => $this->workspace->id,
        'option_id' => $option->id,
        'value' => 'M',
    ]);

    $response = $this->delete(route('user.commerce.products.destroy', $product));

    $response->assertRedirect();
    $response->assertSessionHas('success', __('Product deleted.'));
    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse()
        ->and(ProductMedia::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(ProductColor::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(ProductVariant::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(ProductTierPrice::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(ProductOption::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(ProductOptionValue::query()->where('option_id', $option->id)->exists())->toBeFalse();
});

it('bulk deletes products that have colors, variants, and gallery media', function (): void {
    $product1 = Product::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Product 1',
        'slug' => 'product-1',
        'status' => 'published',
    ]);

    $product2 = Product::query()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Product 2',
        'slug' => 'product-2',
        'status' => 'published',
    ]);

    $color = ProductColor::query()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $product1->id,
        'name' => 'Black',
        'hex_code' => '#000000',
    ]);

    $media = Media::query()->create([
        'name' => 'Sample Image 2',
        'file_name' => 'sample2.jpg',
        'original_name' => 'sample2.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'type' => 'image',
        'size' => 1024,
        'disk' => 'public',
        'path' => 'test/sample2.jpg',
    ]);

    ProductMedia::query()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $product1->id,
        'media_id' => $media->id,
        'color_id' => $color->id,
        'media_type' => 'image',
        'role' => 'gallery',
    ]);

    $response = $this->delete(route('user.commerce.products.bulk-destroy'), [
        'ids' => [$product1->id, $product2->id],
    ]);

    $response->assertRedirect();
    expect(Product::query()->whereIn('id', [$product1->id, $product2->id])->exists())->toBeFalse();
});
