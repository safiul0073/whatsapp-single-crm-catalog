<?php

use App\Models\User;
use App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder;
use App\Modules\Commerce\Jobs\SyncMetaCatalogJob;
use App\Modules\Commerce\Models\Audience;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\CommerceMessageAttempt;
use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\VariantPreset;
use App\Modules\Commerce\Services\CatalogFeedService;
use App\Modules\Commerce\Services\CatalogMessageService;
use App\Modules\Commerce\Services\CatalogSyncService;
use App\Modules\Commerce\Services\GarmentPricingService;
use App\Modules\Commerce\Services\OrderIntakeService;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\ProductService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use App\Modules\WhatsAppCloud\Services\WhatsAppMessagePayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function commerceContext(): array
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);
    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'US Sales',
        'status' => 'connected',
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-1',
        'provider_display_id' => '+14155550100',
    ]);
    $contact = Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'US Buyer', 'phone' => '+14155552671', 'country' => 'US']);
    $conversation = Conversation::query()->create(['workspace_id' => $workspace->id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'contact_id' => $contact->id, 'session_expires_at' => now()->addHours(24)]);

    return compact('user', 'workspace', 'channel', 'contact', 'conversation');
}

function commerceProduct(int $workspaceId): Product
{
    return app(ProductService::class)->create($workspaceId, [
        'name' => 'Performance Jacket',
        'brand' => 'Dhaka Apparel',
        'description' => 'Water-resistant garment',
        'condition' => 'new',
        'audience' => 'adult',
        'country_of_origin' => 'BD',
        'status' => 'active',
        'options' => [
            ['name' => 'Size', 'code' => 'size', 'values' => ['M', 'L']],
            ['name' => 'Material', 'code' => 'material', 'values' => ['Polyester']],
        ],
        'variants' => [[
            'sku' => 'JKT-BLK-M',
            'meta_retailer_id' => 'meta-jkt-blk-m',
            'attributes' => ['size' => 'M', 'color' => 'Black', 'material' => 'Polyester'],
            'price' => 49.95,
            'stock_quantity' => 5,
            'status' => 'active',
        ]],
    ]);
}

function commerceMedia(User $user, string $name, string $type = 'image'): Media
{
    $extension = $type === 'video' ? 'mp4' : 'jpg';

    return Media::query()->create([
        'name' => $name,
        'file_name' => $name.'.'.$extension,
        'original_name' => $name.'.'.$extension,
        'mime_type' => $type === 'video' ? 'video/mp4' : 'image/jpeg',
        'extension' => $extension,
        'type' => $type,
        'size' => 1024,
        'disk' => 'public',
        'path' => 'commerce/'.$name.'.'.$extension,
        'uploaded_by' => $user->id,
    ]);
}

it('renders a merchant public shop with only active products', function (): void {
    $context = commerceContext();
    $activeProduct = commerceProduct($context['workspace']->id);
    $activeProduct->update(['name' => 'Public Performance Jacket', 'slug' => 'public-performance-jacket']);

    Product::query()->create([
        'workspace_id' => $context['workspace']->id,
        'name' => 'Draft Hidden Jacket',
        'slug' => 'draft-hidden-jacket',
        'brand' => 'Dhaka Apparel',
        'description' => 'This product should not be public.',
        'condition' => 'new',
        'audience' => 'adult',
        'country_of_origin' => 'BD',
        'status' => 'draft',
    ]);

    $this->get(route('commerce.products.index', $context['workspace']->slug))
        ->assertOk()
        ->assertSee($context['workspace']->name)
        ->assertSee('Public Performance Jacket')
        ->assertSee(route('commerce.products.public', ['workspace' => $context['workspace']->slug, 'product' => $activeProduct->slug]), false)
        ->assertDontSee('Draft Hidden Jacket');
});

it('renders all active products with category and subcategory filters on the public products page', function (): void {
    $context = commerceContext();
    $parent = Category::query()->create([
        'workspace_id' => $context['workspace']->id,
        'name' => 'Outerwear',
        'slug' => 'outerwear',
        'is_active' => true,
    ]);
    $jackets = Category::query()->create([
        'workspace_id' => $context['workspace']->id,
        'parent_id' => $parent->id,
        'name' => 'Jackets',
        'slug' => 'jackets',
        'is_active' => true,
    ]);
    $shirts = Category::query()->create([
        'workspace_id' => $context['workspace']->id,
        'parent_id' => $parent->id,
        'name' => 'Shirts',
        'slug' => 'shirts',
        'is_active' => true,
    ]);
    $jacket = commerceProduct($context['workspace']->id);
    $jacket->update(['name' => 'Public Filter Jacket', 'slug' => 'public-filter-jacket', 'category_id' => $jackets->id]);
    $shirt = commerceProduct($context['workspace']->id);
    $shirt->update(['name' => 'Public Filter Shirt', 'slug' => 'public-filter-shirt', 'category_id' => $shirts->id]);

    $this->get(route('commerce.products.shortcut'))
        ->assertOk()
        ->assertSee('All active products')
        ->assertSee('Outerwear')
        ->assertSee('Jackets')
        ->assertSee('Public Filter Jacket')
        ->assertSee('Public Filter Shirt')
        ->assertSee(route('commerce.products.public', ['workspace' => $context['workspace']->slug, 'product' => $jacket->slug]), false);

    $this->get(route('commerce.products.shortcut', ['category' => $parent->id, 'subcategory' => $jackets->id]))
        ->assertOk()
        ->assertSee('Public Filter Jacket')
        ->assertDontSee('Public Filter Shirt');
});

it('renders active commerce products on the home page products section', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);

    $context = commerceContext();
    $activeProduct = commerceProduct($context['workspace']->id);
    $activeProduct->update(['name' => 'Homepage Active Jacket', 'slug' => 'homepage-active-jacket']);
    $front = commerceMedia($context['user'], 'homepage-active-front');
    $front->update(['alt' => 'Homepage active front']);

    app(ProductService::class)->updateGallery($activeProduct, [
        ['id' => $front->id, 'alt_text' => 'Homepage active front', 'is_primary' => true],
    ]);

    Product::query()->create([
        'workspace_id' => $context['workspace']->id,
        'name' => 'Homepage Draft Jacket',
        'slug' => 'homepage-draft-jacket',
        'brand' => 'Dhaka Apparel',
        'description' => 'This draft product should not render on the home page.',
        'condition' => 'new',
        'audience' => 'adult',
        'country_of_origin' => 'BD',
        'status' => 'draft',
    ]);

    Catalog::query()->create([
        'workspace_id' => $context['workspace']->id,
        'channel_account_id' => $context['channel']->id,
        'meta_catalog_id' => 'catalog-homepage',
        'feed_token' => str_repeat('h', 64),
        'is_active' => true,
    ]);

    $productUrl = route('commerce.products.public', ['workspace' => $context['workspace']->slug, 'product' => $activeProduct->fresh()->slug]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('WhatsApp Catalog')
        ->assertSee('Homepage Active Jacket')
        ->assertSee('Homepage active front')
        ->assertSee('USD 49.95')
        ->assertSee($productUrl, false)
        ->assertSee('https://wa.me/14155550100', false)
        ->assertDontSee('Homepage Draft Jacket');
});

it('redirects the legacy product URL when the slug is unique', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    $product->update(['slug' => 'legacy-jacket']);

    $this->get(route('commerce.products.legacy', $product->slug))
        ->assertRedirect(route('commerce.products.public', ['workspace' => $context['workspace']->slug, 'product' => 'legacy-jacket']));
});

it('renders a modern active public product detail page', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    $product->update(['name' => 'Modern Detail Jacket', 'slug' => 'modern-detail-jacket']);
    $front = commerceMedia($context['user'], 'public-front');

    app(ProductService::class)->updateGallery($product, [
        ['id' => $front->id, 'alt_text' => 'Front view', 'is_primary' => true],
    ]);

    Catalog::query()->create([
        'workspace_id' => $context['workspace']->id,
        'channel_account_id' => $context['channel']->id,
        'meta_catalog_id' => 'catalog-public',
        'feed_token' => str_repeat('b', 64),
        'is_active' => true,
    ]);

    $this->get(route('commerce.products.public', ['workspace' => $context['workspace']->slug, 'product' => $product->fresh()->slug]))
        ->assertOk()
        ->assertSee('Modern Detail Jacket')
        ->assertSee('49.95')
        ->assertSee('JKT-BLK-M')
        ->assertSee('Order on WhatsApp')
        ->assertSee('https://wa.me/14155550100', false)
        ->assertSee('Front view')
        ->assertSee('Catalog')
        ->assertDontSee('ADD TO SHOPPING CART')
        ->assertDontSee('https://wa.me/?text=', false)
        ->assertDontSee('Furniture')
        ->assertDontSee('Payment:')
        ->assertDontSee('EMI available')
        ->assertDontSee('2 - 5 working days')
        ->assertDontSee('Standard delivery timeline');
});

it('does not render inactive public product detail pages', function (string $status): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    $product->update(['slug' => 'inactive-public-product-'.$status, 'status' => $status]);

    $this->get(route('commerce.products.public', ['workspace' => $context['workspace']->slug, 'product' => $product->slug]))->assertNotFound();
})->with(['draft', 'archived']);

it('shows commerce sidebar links to a permitted web user', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($user);

    $sidebar = view('components.layouts.partials.user-sidebar')->render();

    expect($user->can('commerce.view'))->toBeTrue()
        ->and($user->can('commerce.manage'))->toBeTrue()
        ->and($sidebar)
        ->toContain('>Commerce<')
        ->toContain('>Products<')
        ->toContain('>Categories<')
        ->toContain('>Brands<')
        ->toContain('>Audiences<')
        ->toContain('>Orders<')
        ->toContain('>Meta Catalog<');
});

it('provides a dedicated workspace-scoped category management page', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);

    $this->actingAs($context['user'])
        ->post(route('user.commerce.categories.store'), [
            'name' => 'Outerwear',
            'is_active' => true,
        ])
        ->assertRedirect();

    $category = Category::query()
        ->where('workspace_id', $context['workspace']->id)
        ->where('name', 'Outerwear')
        ->firstOrFail();

    $this->get(route('user.commerce.categories.index'))
        ->assertOk()
        ->assertSee('Product categories')
        ->assertSee('Outerwear');

    $this->put(route('user.commerce.categories.update', $category), [
        'name' => 'Jackets & Outerwear',
        'is_active' => true,
    ])->assertRedirect();

    expect($category->fresh()->name)->toBe('Jackets & Outerwear')
        ->and($category->fresh()->workspace_id)->toBe($context['workspace']->id);
});

it('manages table-backed brands and audiences on separate pages', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($context['user']);

    $this->post(route('user.commerce.brands.store'), ['name' => 'Dhaka Apparel', 'is_active' => true])->assertRedirect();
    $this->post(route('user.commerce.audiences.store'), ['name' => 'Women', 'is_active' => true])->assertRedirect();

    $brand = Brand::query()->where('workspace_id', $context['workspace']->id)->where('name', 'Dhaka Apparel')->firstOrFail();
    $audience = Audience::query()->where('workspace_id', $context['workspace']->id)->where('name', 'Women')->firstOrFail();

    $this->get(route('user.commerce.brands.index'))->assertOk()->assertSee('Dhaka Apparel');
    $this->get(route('user.commerce.audiences.index'))->assertOk()->assertSee('Women');

    expect($brand->workspace_id)->toBe($context['workspace']->id)
        ->and($audience->workspace_id)->toBe($context['workspace']->id);
});

it('deletes products individually and in bulk inside the active workspace', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($context['user']);

    $single = Product::query()->create([
        'workspace_id' => $context['workspace']->id,
        'name' => 'Single Delete Shirt',
        'slug' => 'single-delete-shirt',
        'status' => 'draft',
    ]);
    $bulkOne = Product::query()->create([
        'workspace_id' => $context['workspace']->id,
        'name' => 'Bulk Delete Shirt',
        'slug' => 'bulk-delete-shirt',
        'status' => 'draft',
    ]);
    $bulkTwo = Product::query()->create([
        'workspace_id' => $context['workspace']->id,
        'name' => 'Bulk Delete Jacket',
        'slug' => 'bulk-delete-jacket',
        'status' => 'draft',
    ]);

    $this->delete(route('user.commerce.products.destroy', $single))->assertRedirect();
    $this->delete(route('user.commerce.products.bulk-destroy'), ['ids' => [$bulkOne->id, $bulkTwo->id]])->assertRedirect();

    $this->assertDatabaseMissing('commerce_products', ['id' => $single->id]);
    $this->assertDatabaseMissing('commerce_products', ['id' => $bulkOne->id]);
    $this->assertDatabaseMissing('commerce_products', ['id' => $bulkTwo->id]);
});

it('deletes empty categories brands and audiences individually and in bulk', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($context['user']);

    $singleCategory = Category::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Single Category', 'slug' => 'single-category']);
    $bulkCategories = Category::query()->insert([
        ['workspace_id' => $context['workspace']->id, 'name' => 'Bulk Category One', 'slug' => 'bulk-category-one', 'created_at' => now(), 'updated_at' => now()],
        ['workspace_id' => $context['workspace']->id, 'name' => 'Bulk Category Two', 'slug' => 'bulk-category-two', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $categoryIds = Category::query()->where('workspace_id', $context['workspace']->id)->where('slug', 'like', 'bulk-category-%')->pluck('id')->all();

    $singleBrand = Brand::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Single Brand', 'slug' => 'single-brand']);
    $bulkBrand = Brand::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Bulk Brand', 'slug' => 'bulk-brand']);
    $singleAudience = Audience::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Single Audience', 'slug' => 'single-audience']);
    $bulkAudience = Audience::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Bulk Audience', 'slug' => 'bulk-audience']);

    expect($bulkCategories)->toBeTrue();

    $this->delete(route('user.commerce.categories.destroy', $singleCategory))->assertRedirect();
    $this->delete(route('user.commerce.categories.bulk-destroy'), ['ids' => $categoryIds])->assertRedirect();
    $this->delete(route('user.commerce.brands.destroy', $singleBrand))->assertRedirect();
    $this->delete(route('user.commerce.brands.bulk-destroy'), ['ids' => [$bulkBrand->id]])->assertRedirect();
    $this->delete(route('user.commerce.audiences.destroy', $singleAudience))->assertRedirect();
    $this->delete(route('user.commerce.audiences.bulk-destroy'), ['ids' => [$bulkAudience->id]])->assertRedirect();

    $this->assertDatabaseMissing('commerce_categories', ['id' => $singleCategory->id]);
    foreach ($categoryIds as $categoryId) {
        $this->assertDatabaseMissing('commerce_categories', ['id' => $categoryId]);
    }
    $this->assertDatabaseMissing('commerce_brands', ['id' => $singleBrand->id]);
    $this->assertDatabaseMissing('commerce_brands', ['id' => $bulkBrand->id]);
    $this->assertDatabaseMissing('commerce_audiences', ['id' => $singleAudience->id]);
    $this->assertDatabaseMissing('commerce_audiences', ['id' => $bulkAudience->id]);
});

it('blocks bulk deletion of in-use taxonomy records and records from other workspaces', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($context['user']);

    $category = Category::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Assigned Category', 'slug' => 'assigned-category']);
    $brand = Brand::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Assigned Brand', 'slug' => 'assigned-brand']);
    $audience = Audience::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Assigned Audience', 'slug' => 'assigned-audience']);

    Product::query()->create([
        'workspace_id' => $context['workspace']->id,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'audience_id' => $audience->id,
        'name' => 'Assigned Product',
        'slug' => 'assigned-product',
        'status' => 'draft',
    ]);

    $this->from(route('user.commerce.categories.index'))
        ->delete(route('user.commerce.categories.bulk-destroy'), ['ids' => [$category->id]])
        ->assertRedirect(route('user.commerce.categories.index'))
        ->assertSessionHasErrors('ids');
    $this->from(route('user.commerce.brands.index'))
        ->delete(route('user.commerce.brands.bulk-destroy'), ['ids' => [$brand->id]])
        ->assertRedirect(route('user.commerce.brands.index'))
        ->assertSessionHasErrors('ids');
    $this->from(route('user.commerce.audiences.index'))
        ->delete(route('user.commerce.audiences.bulk-destroy'), ['ids' => [$audience->id]])
        ->assertRedirect(route('user.commerce.audiences.index'))
        ->assertSessionHasErrors('ids');

    $otherContext = commerceContext();
    $otherProduct = Product::query()->create([
        'workspace_id' => $otherContext['workspace']->id,
        'name' => 'Other Workspace Product',
        'slug' => 'other-workspace-product',
        'status' => 'draft',
    ]);

    $this->delete(route('user.commerce.products.bulk-destroy'), ['ids' => [$otherProduct->id]])->assertNotFound();
});

it('seeds one hundred realistic products with more than forty five live images', function (): void {
    $context = commerceContext();

    $this->seed(CommerceDemoSeeder::class);

    $products = Product::query()->where('workspace_id', $context['workspace']->id)->where('slug', 'like', 'demo-%')->get();
    $images = Media::query()->where('uploaded_by', $context['user']->id)->where('file_name', 'like', 'commerce-demo-%')->get();

    expect($products)->toHaveCount(100)
        ->and(ProductVariant::query()->where('workspace_id', $context['workspace']->id)->where('sku', 'like', 'DEMO-%')->count())->toBe(400)
        ->and($images)->toHaveCount(60)
        ->and($images->every(fn (Media $media): bool => str_starts_with($media->path, 'https://')))->toBeTrue()
        ->and($images->every(fn (Media $media): bool => filled($media->alt) && str_contains($media->path, 'loremflickr.com/960/1200')))->toBeTrue()
        ->and($images->first()->url)->toBe($images->first()->path)
        ->and($products->pluck('name')->all())->toContain('Premium Double-Face Wool Blend Coat')
        ->and($products->every(fn (Product $product): bool => filled($product->brand_id) && filled($product->audience_id) && filled($product->primary_media_id) && str_contains((string) $product->description, 'WhatsApp catalog selling')))->toBeTrue();

    Permission::findOrCreate('commerce.view', 'web');
    $context['user']->givePermissionTo('commerce.view');
    $this->actingAs($context['user'])
        ->get(route('user.commerce.products.index'))
        ->assertOk()
        ->assertSee('data-product-table', false)
        ->assertSee('data-product-grid', false)
        ->assertSee('data-commerce-help="products"', false)
        ->assertSeeText('Product management help')
        ->assertSeeText('Complete WhatsApp selling workflow')
        ->assertSeeText('Showing 1-20 of 100 items');
});

it('shows feature-specific help across commerce management pages', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($context['user']);

    $pages = [
        route('user.commerce.products.create') => 'product_form',
        route('user.commerce.categories.index') => 'categories',
        route('user.commerce.brands.index') => 'brands',
        route('user.commerce.audiences.index') => 'audiences',
        route('user.commerce.variants.index') => 'categories',
        route('user.commerce.catalog') => 'catalog',
        route('user.commerce.orders.index') => 'orders',
    ];

    foreach ($pages as $url => $helpKey) {
        $this->get($url)
            ->assertOk()
            ->assertSee('data-commerce-help="'.$helpKey.'"', false)
            ->assertSeeText('Complete WhatsApp selling workflow');
    }
});

it('stores arbitrary apparel options and variants inside a workspace', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);

    expect($product->options)->toHaveCount(2)
        ->and($product->options->firstWhere('code', 'material')->values->pluck('value')->all())->toBe(['Polyester'])
        ->and($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->attributes['color'])->toBe('Black');
});

it('persists an ordered gallery with one primary image and one video', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    $front = commerceMedia($context['user'], 'front');
    $detail = commerceMedia($context['user'], 'detail');
    $video = commerceMedia($context['user'], 'fit-video', 'video');

    app(ProductService::class)->updateGallery($product, [
        ['id' => $front->id, 'alt_text' => 'Front view', 'is_primary' => true],
        ['id' => $detail->id, 'alt_text' => 'Fabric detail', 'is_primary' => false],
        ['id' => $video->id, 'alt_text' => 'Fit video', 'is_primary' => false],
    ]);

    expect($product->fresh()->primary_media_id)->toBe($front->id)
        ->and($product->fresh()->gallery)->toHaveCount(3)
        ->and($product->fresh()->gallery->pluck('media_id')->all())->toBe([$front->id, $detail->id, $video->id])
        ->and($product->fresh()->gallery->where('is_primary', true))->toHaveCount(1);
});

it('creates a resumable draft and persists its gallery through wizard routes', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);
    $brand = Brand::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Dhaka Apparel', 'slug' => 'dhaka-apparel', 'is_active' => true]);
    $audience = Audience::query()->create(['workspace_id' => $context['workspace']->id, 'name' => 'Women', 'slug' => 'women', 'is_active' => true]);

    $response = $this->actingAs($context['user'])->post(route('user.commerce.products.store'), [
        'name' => 'Everyday Oxford Shirt',
        'brand_id' => $brand->id,
        'description' => 'A versatile cotton shirt.',
        'condition' => 'new',
        'audience_id' => $audience->id,
        'country_of_origin' => 'BD',
    ]);

    $product = Product::query()->where('workspace_id', $context['workspace']->id)->where('name', 'Everyday Oxford Shirt')->firstOrFail();
    $response->assertRedirect(route('user.commerce.products.edit', ['product' => $product, 'step' => 2]));
    expect($product->status)->toBe('draft')
        ->and($product->wizard_step)->toBe(2)
        ->and($product->brand_id)->toBe($brand->id)
        ->and($product->audience_id)->toBe($audience->id)
        ->and($product->brand)->toBe('Dhaka Apparel')
        ->and($product->audience)->toBe('Women');

    $front = commerceMedia($context['user'], 'wizard-front');
    $galleryResponse = $this->put(route('user.commerce.products.gallery.update', $product), [
        'media' => [[
            'id' => $front->id,
            'alt_text' => 'Front view of the Oxford shirt',
            'is_primary' => true,
        ]],
    ]);

    $galleryResponse->assertRedirect(route('user.commerce.products.edit', ['product' => $product, 'step' => 4]));
    expect($product->fresh()->primary_media_id)->toBe($front->id)
        ->and($product->fresh()->wizard_step)->toBe(4)
        ->and($product->fresh()->gallery)->toHaveCount(1);
});

it('generates stable variant combinations from saved options', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);

    $preview = app(ProductService::class)->variantPreview($product);

    expect($preview)->toHaveCount(2)
        ->and(collect($preview)->pluck('attributes.size')->all())->toBe(['M', 'L'])
        ->and($preview[0]['sku'])->not->toBeEmpty();
});

it('generates a Meta CSV feed with merchant URLs and configured currency prices', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    $catalog = Catalog::query()->create(['workspace_id' => $context['workspace']->id, 'channel_account_id' => $context['channel']->id, 'meta_catalog_id' => 'catalog-1', 'feed_token' => str_repeat('a', 64), 'currency' => 'BDT']);

    $response = app(CatalogFeedService::class)->response($catalog);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('meta-jkt-blk-m')
        ->toContain('49.95 BDT')
        ->toContain(route('commerce.products.public', ['workspace' => $context['workspace']->slug, 'product' => $product->slug]))
        ->toContain('Polyester')
        ->and($catalog->fresh()->last_item_count)->toBe(1);
});

it('builds WhatsApp Cloud catalog and multi-product template payloads', function (): void {
    $builder = app(WhatsAppMessagePayloadBuilder::class);

    $catalogPayload = $builder->build('+1 (415) 555-2671', [
        'type' => 'catalog_message',
        'body' => 'Browse our catalog.',
        'thumbnail_product_retailer_id' => 'meta-jkt-blk-m',
    ]);
    $templatePayload = $builder->build('+1 (415) 555-2671', [
        'type' => 'template',
        'template_name' => 'multi_product_offer',
        'language' => 'en_US',
        'components' => [[
            'type' => 'button',
            'sub_type' => 'MPM',
            'index' => '0',
            'parameters' => [[
                'type' => 'action',
                'action' => [
                    'thumbnail_product_retailer_id' => 'meta-jkt-blk-m',
                    'sections' => [[
                        'title' => 'Jackets',
                        'product_items' => [['product_retailer_id' => 'meta-jkt-blk-m']],
                    ]],
                ],
            ]],
        ]],
    ]);

    expect($catalogPayload['messaging_product'])->toBe('whatsapp')
        ->and($catalogPayload['type'])->toBe('interactive')
        ->and($catalogPayload['interactive']['type'])->toBe('catalog_message')
        ->and($catalogPayload['interactive']['action']['parameters']['thumbnail_product_retailer_id'])->toBe('meta-jkt-blk-m')
        ->and(data_get($templatePayload, 'template.components.0.sub_type'))->toBe('MPM')
        ->and(data_get($templatePayload, 'template.components.0.parameters.0.action.sections.0.product_items.0.product_retailer_id'))->toBe('meta-jkt-blk-m');
});

it('includes additional gallery images in the Meta feed', function (): void {
    config(['app.url' => 'https://store.example.com', 'app.asset_url' => 'https://store.example.com']);
    URL::forceRootUrl('https://store.example.com');
    URL::forceScheme('https');
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    $front = commerceMedia($context['user'], 'feed-front');
    $back = commerceMedia($context['user'], 'feed-back');
    app(ProductService::class)->updateGallery($product, [
        ['id' => $front->id, 'alt_text' => 'Front', 'is_primary' => true],
        ['id' => $back->id, 'alt_text' => 'Back', 'is_primary' => false],
    ]);
    $catalog = Catalog::query()->create(['workspace_id' => $context['workspace']->id, 'channel_account_id' => $context['channel']->id, 'meta_catalog_id' => 'catalog-1', 'feed_token' => str_repeat('c', 64), 'currency' => 'USD']);

    $response = app(CatalogFeedService::class)->response($catalog);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('https://store.example.com/storage/commerce/feed-back.jpg');
});

it('queues idempotent direct catalog synchronization after capability checks pass', function (): void {
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'catalog-api', 'name' => 'US Store'], 200)]);
    config(['app.url' => 'https://store.example.com', 'app.asset_url' => 'https://store.example.com']);
    URL::forceRootUrl('https://store.example.com');
    URL::forceScheme('https');
    $context = commerceContext();
    $context['workspace']->update(['settings' => array_merge((array) $context['workspace']->settings, ['commerce' => ['currency' => 'USD']])]);
    $context['channel']->update(['credentials' => ['access_token' => 'secret-token']]);
    $product = commerceProduct($context['workspace']->id);
    $front = commerceMedia($context['user'], 'api-front');
    app(ProductService::class)->updateGallery($product, [['id' => $front->id, 'alt_text' => 'Front', 'is_primary' => true]]);
    $catalog = Catalog::query()->create(['workspace_id' => $context['workspace']->id, 'channel_account_id' => $context['channel']->id, 'meta_catalog_id' => 'catalog-api', 'feed_token' => str_repeat('d', 64), 'sync_mode' => 'api', 'currency' => 'USD']);

    $run = app(CatalogSyncService::class)->queue($catalog);

    expect($run->status)->toBe('queued');
    Queue::assertPushed(SyncMetaCatalogJob::class, fn (SyncMetaCatalogJob $job): bool => $job->runId === $run->id);
});

it('persists an outbound product attempt once when the same send is retried', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    Catalog::query()->create([
        'workspace_id' => $context['workspace']->id,
        'channel_account_id' => $context['channel']->id,
        'meta_catalog_id' => 'catalog-message',
        'feed_token' => str_repeat('m', 64),
    ]);
    $channels = Mockery::mock(ChannelManager::class);
    $channels->shouldReceive('sendMessage')->once()->andReturn([
        'ok' => true,
        'status' => 'sent',
        'provider_message_id' => 'wamid.product.1',
        'response' => ['messages' => [['id' => 'wamid.product.1']]],
    ]);
    $service = new CatalogMessageService($channels);

    $first = $service->sendProduct($context['conversation'], $product->variants->first()->id);
    $second = $service->sendProduct($context['conversation'], $product->variants->first()->id);

    expect($first->is($second))->toBeTrue()
        ->and($first->provider_message_id)->toBe('wamid.product.1')
        ->and(Message::query()->where('direction', 'outbound')->count())->toBe(1)
        ->and(CommerceMessageAttempt::query()->count())->toBe(1);
});

it('reports the customer service window and catalog readiness to the inbox drawer', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo('commerce.view');
    $context['conversation']->update(['session_expires_at' => null]);
    commerceProduct($context['workspace']->id);
    $catalog = Catalog::query()->create([
        'workspace_id' => $context['workspace']->id,
        'channel_account_id' => $context['channel']->id,
        'meta_catalog_id' => 'catalog-readiness',
        'feed_token' => str_repeat('r', 64),
        'sync_mode' => 'feed',
    ]);

    $this->actingAs($context['user'])
        ->getJson(route('user.commerce.conversations.products', $context['conversation']))
        ->assertSuccessful()
        ->assertJsonPath('session_active', false)
        ->assertJsonPath('catalog.connected', true)
        ->assertJsonPath('catalog.ready', false)
        ->assertJsonPath('catalog.item_count', 0);

    $catalog->update([
        'last_sync_status' => 'completed',
        'last_successful_at' => now(),
        'last_item_count' => 2,
    ]);

    $this->getJson(route('user.commerce.conversations.products', $context['conversation']))
        ->assertSuccessful()
        ->assertJsonPath('catalog.ready', true)
        ->assertJsonPath('catalog.item_count', 2);
});

it('requires a buyer reply before sending interactive catalog products', function (): void {
    $context = commerceContext();
    $context['conversation']->update(['session_expires_at' => null]);
    $product = commerceProduct($context['workspace']->id);
    Catalog::query()->create([
        'workspace_id' => $context['workspace']->id,
        'channel_account_id' => $context['channel']->id,
        'meta_catalog_id' => 'catalog-expired-session',
        'feed_token' => str_repeat('e', 64),
    ]);
    $channels = Mockery::mock(ChannelManager::class);
    $channels->shouldNotReceive('sendMessage');

    (new CatalogMessageService($channels))->sendProduct(
        $context['conversation']->fresh(['channelAccount', 'contact']),
        $product->variants->first()->id,
    );
})->throws(ValidationException::class, 'wait for the buyer to reply');

it('creates one immutable order for duplicate WhatsApp cart webhooks', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    Catalog::query()->create(['workspace_id' => $context['workspace']->id, 'channel_account_id' => $context['channel']->id, 'meta_catalog_id' => 'catalog-1', 'feed_token' => str_repeat('b', 64)]);
    $message = ['id' => 'wamid-order-1', 'type' => 'order', 'order' => ['catalog_id' => 'catalog-1', 'product_items' => [['product_retailer_id' => 'meta-jkt-blk-m', 'quantity' => 2, 'item_price' => '49.95', 'currency' => 'USD']]]];

    $first = app(OrderIntakeService::class)->intake($context['channel'], $context['contact'], $context['conversation'], $message);
    $second = app(OrderIntakeService::class)->intake($context['channel'], $context['contact'], $context['conversation'], $message);

    expect($first->is($second))->toBeTrue()
        ->and(Order::query()->count())->toBe(1)
        ->and($first->items->first()->product_name)->toBe($product->name)
        ->and((float) $first->subtotal)->toBe(99.90);
});

it('deducts inventory once when paid and restores it once on cancellation', function (): void {
    $context = commerceContext();
    $variant = commerceProduct($context['workspace']->id)->variants->first();
    $message = ['id' => 'wamid-order-2', 'type' => 'order', 'order' => ['catalog_id' => 'catalog-1', 'product_items' => [['product_retailer_id' => $variant->meta_retailer_id, 'quantity' => 2, 'item_price' => '49.95', 'currency' => 'USD']]]];
    $order = app(OrderIntakeService::class)->intake($context['channel'], $context['contact'], $context['conversation'], $message);
    $order->update(['status' => 'awaiting_payment']);
    $workflow = app(OrderWorkflowService::class);

    $paid = $workflow->transition($order->fresh(), 'paid');
    $cancelled = $workflow->transition($paid, 'cancelled');

    expect($variant->fresh()->stock_quantity)->toBe(5)
        ->and($cancelled->inventory_adjusted_at)->not->toBeNull()
        ->and($cancelled->inventory_restored_at)->not->toBeNull()
        ->and(InventoryMovement::query()->count())->toBe(2);
});

it('rejects payment when stock is insufficient', function (): void {
    $context = commerceContext();
    $variant = commerceProduct($context['workspace']->id)->variants->first();
    $message = ['id' => 'wamid-order-3', 'type' => 'order', 'order' => ['product_items' => [['product_retailer_id' => $variant->meta_retailer_id, 'quantity' => 10, 'item_price' => '49.95', 'currency' => 'USD']]]];
    $order = app(OrderIntakeService::class)->intake($context['channel'], $context['contact'], $context['conversation'], $message);
    $order->update(['status' => 'awaiting_payment']);

    app(OrderWorkflowService::class)->transition($order->fresh(), 'paid');
})->throws(ValidationException::class, 'Insufficient stock');

it('calculates garment single-piece vs wholesale bulk shipping and costs accurately', function (): void {
    $pricingService = app(GarmentPricingService::class);
    $product = new Product([
        'name' => '180 GSM Combed Cotton T-Shirt',
        'single_piece_price' => 9.00,
        'wholesale_price' => 6.50,
        'default_unit_weight_kg' => 0.030,
        'fabric_gsm' => '180 GSM',
        'material' => '100% Combed Cotton',
    ]);

    // 1 single piece calculation: $9.00 piece + 1 kg min shipping ($50) = $59 total ($59/pc)
    $singleCalculation = $pricingService->calculateCost(
        product: $product,
        quantity: 1,
        unitPrice: 9.00,
        unitWeightKg: 0.030,
        baseShippingRatePerKg: 50.00,
        minShippingKg: 1.0
    );

    expect($singleCalculation['total_quantity'])->toBe(1)
        ->and($singleCalculation['unit_price'])->toBe(9.00)
        ->and($singleCalculation['garment_subtotal'])->toBe(9.00)
        ->and($singleCalculation['total_weight_kg'])->toBe(0.03)
        ->and($singleCalculation['chargeable_weight_kg'])->toBe(1.0)
        ->and($singleCalculation['shipping_cost'])->toBe(50.00)
        ->and($singleCalculation['total_landed_cost'])->toBe(59.00)
        ->and($singleCalculation['effective_price_per_unit'])->toBe(59.00)
        ->and($singleCalculation['is_wholesale'])->toBeFalse();

    // 100 pieces wholesale calculation: 100 * $6.50 = $650 + 3 kg shipping (3 * $50 = $150) = $800 total ($8.00/pc)
    $bulkCalculation = $pricingService->calculateCost(
        product: $product,
        quantity: 100,
        unitWeightKg: 0.030,
        baseShippingRatePerKg: 50.00,
        minShippingKg: 1.0
    );

    expect($bulkCalculation['total_quantity'])->toBe(100)
        ->and($bulkCalculation['unit_price'])->toBe(6.50)
        ->and($bulkCalculation['garment_subtotal'])->toBe(650.00)
        ->and($bulkCalculation['total_weight_kg'])->toBe(3.0)
        ->and($bulkCalculation['chargeable_weight_kg'])->toBe(3.0)
        ->and($bulkCalculation['shipping_cost'])->toBe(150.00)
        ->and($bulkCalculation['total_landed_cost'])->toBe(800.00)
        ->and($bulkCalculation['effective_price_per_unit'])->toBe(8.00)
        ->and($bulkCalculation['is_wholesale'])->toBeTrue()
        ->and($bulkCalculation['savings_per_unit'])->toBe(51.00);
});

it('syncs garment color swatches and resolves two-price model correctly', function (): void {
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    $product->update([
        'single_piece_price' => 9.00,
        'wholesale_price' => 6.50,
    ]);
    $service = app(ProductService::class);

    // Sync colors
    $service->syncColors($product, [
        ['name' => 'Royal Blue', 'hex_code' => '#1E3A8A', 'color_family' => 'Blue'],
        ['name' => 'Jet Black', 'hex_code' => '#111827', 'color_family' => 'Black'],
        ['name' => '', 'hex_code' => '#10B981', 'color_family' => 'Green'], // unknown name, fallback to hex/family
    ]);

    expect($product->fresh()->colors()->count())->toBe(3)
        ->and($product->colors()->where('hex_code', '#1E3A8A')->first()->display_name)->toBe('Royal Blue')
        ->and($product->colors()->where('hex_code', '#10B981')->first()->display_name)->toBe('Green (#10B981)')
        ->and($product->fresh()->resolveUnitPrice(1, 'single'))->toBe(9.00)
        ->and($product->fresh()->resolveUnitPrice(100, 'wholesale'))->toBe(6.50);
});

it('provides reusable variant size presets CRUD and allows applying same sizes across multiple products', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($context['user']);

    // 1. Create a reusable variant preset
    $this->post(route('user.commerce.variants.store'), [
        'name' => 'Adult Standard Sizes',
        'values_csv' => 'S, M, L, XL, XXL',
        'is_active' => true,
    ])->assertRedirect();

    $preset = VariantPreset::query()->where('workspace_id', $context['workspace']->id)->where('name', 'Adult Standard Sizes')->firstOrFail();
    expect($preset->values)->toBe(['S', 'M', 'L', 'XL', 'XXL']);

    // 2. View variants preset index page
    $this->get(route('user.commerce.variants.index'))
        ->assertOk()
        ->assertSee('Adult Standard Sizes')
        ->assertSee('XXL');

    // 3. Update the preset
    $this->put(route('user.commerce.variants.update', $preset), [
        'name' => 'Adult Standard (S–3XL)',
        'values_csv' => 'S, M, L, XL, XXL, 3XL',
        'is_active' => true,
    ])->assertRedirect();

    expect($preset->fresh()->name)->toBe('Adult Standard (S–3XL)')
        ->and($preset->fresh()->values)->toBe(['S', 'M', 'L', 'XL', 'XXL', '3XL']);

    // 4. Apply these sizes to Product 1 via Step 2
    $product1 = commerceProduct($context['workspace']->id);
    $this->put(route('user.commerce.products.options.update', $product1), [
        'sizes' => $preset->fresh()->values,
        'colors' => [
            ['name' => 'Royal Blue', 'hex_code' => '#1E3A8A'],
            ['name' => 'Jet Black', 'hex_code' => '#111827'],
        ],
    ])->assertRedirect(route('user.commerce.products.edit', ['product' => $product1, 'step' => 3]));

    // 5. Apply the same sizes to Product 2 via Step 2
    $product2 = commerceProduct($context['workspace']->id);
    $this->put(route('user.commerce.products.options.update', $product2), [
        'sizes' => $preset->fresh()->values,
        'colors' => [
            ['name' => 'Heather Grey', 'hex_code' => '#6B7280'],
        ],
    ])->assertRedirect(route('user.commerce.products.edit', ['product' => $product2, 'step' => 3]));

    $p1Options = $product1->fresh()->options()->where('code', 'size')->first();
    $p2Options = $product2->fresh()->options()->where('code', 'size')->first();

    expect($p1Options->values->pluck('value')->all())->toBe(['S', 'M', 'L', 'XL', 'XXL', '3XL'])
        ->and($p2Options->values->pluck('value')->all())->toBe(['S', 'M', 'L', 'XL', 'XXL', '3XL']);

    // 6. Delete preset
    $this->delete(route('user.commerce.variants.destroy', $preset))->assertRedirect();
    $this->assertDatabaseMissing('commerce_variant_presets', ['id' => $preset->id]);
});

it('supports color-dedicated multi-image galleries and connects them to swatches and public storefront', function (): void {
    Permission::findOrCreate('commerce.view', 'web');
    Permission::findOrCreate('commerce.manage', 'web');
    $context = commerceContext();
    $context['user']->givePermissionTo(['commerce.view', 'commerce.manage']);
    $this->actingAs($context['user']);

    $product = commerceProduct($context['workspace']->id);
    $media1 = commerceMedia($context['user'], 'Royal Blue Front');
    $media2 = commerceMedia($context['user'], 'Royal Blue Back');
    $media3 = commerceMedia($context['user'], 'Jet Black Front');
    $media4 = commerceMedia($context['user'], 'Jet Black Texture');

    // Create 2 colors via step 2
    $service = app(ProductService::class);
    $service->syncColors($product, [
        ['name' => 'Royal Blue', 'hex_code' => '#1E3A8A', 'color_family' => 'Blue'],
        ['name' => 'Jet Black', 'hex_code' => '#111827', 'color_family' => 'Black'],
    ]);

    $blueColor = $product->colors()->where('name', 'Royal Blue')->firstOrFail();
    $blackColor = $product->colors()->where('name', 'Jet Black')->firstOrFail();

    // Submit Step 3 Gallery with 2 photos for Blue, 2 photos for Black
    $response = $this->put(route('user.commerce.products.gallery.update', $product), [
        'media' => [
            ['id' => $media1->id, 'color_id' => $blueColor->id, 'alt_text' => 'Royal Blue Front', 'is_primary' => true],
            ['id' => $media2->id, 'color_id' => $blueColor->id, 'alt_text' => 'Royal Blue Back', 'is_primary' => false],
            ['id' => $media3->id, 'color_id' => $blackColor->id, 'alt_text' => 'Jet Black Front', 'is_primary' => false],
            ['id' => $media4->id, 'color_id' => $blackColor->id, 'alt_text' => 'Jet Black Texture', 'is_primary' => false],
        ],
        'colors' => [
            ['id' => $blueColor->id, 'swatch_media_id' => $media1->id],
            ['id' => $blackColor->id, 'swatch_media_id' => $media3->id],
        ],
    ]);

    $response->assertRedirect(route('user.commerce.products.edit', ['product' => $product, 'step' => 4]));

    // Check Blue Color Gallery
    $blueGallery = $blueColor->fresh()->gallery;
    expect($blueGallery->count())->toBe(2)
        ->and($blueGallery->pluck('media_id')->all())->toBe([$media1->id, $media2->id])
        ->and($blueColor->fresh()->swatch_media_id)->toBe($media1->id);

    // Check Black Color Gallery
    $blackGallery = $blackColor->fresh()->gallery;
    expect($blackGallery->count())->toBe(2)
        ->and($blackGallery->pluck('media_id')->all())->toBe([$media3->id, $media4->id])
        ->and($blackColor->fresh()->swatch_media_id)->toBe($media3->id);

    // Check Public Storefront
    $product->update(['status' => 'active', 'wizard_step' => 5]);
    $this->get(route('commerce.products.public', ['workspace' => $context['workspace']->slug, 'product' => $product->slug]))
        ->assertOk()
        ->assertSee('Royal Blue')
        ->assertSee('Jet Black')
        ->assertSee($product->name);
});
