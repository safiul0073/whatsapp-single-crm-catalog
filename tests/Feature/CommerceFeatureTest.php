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
use App\Modules\Commerce\Services\CatalogFeedService;
use App\Modules\Commerce\Services\CatalogMessageService;
use App\Modules\Commerce\Services\CatalogSyncService;
use App\Modules\Commerce\Services\OrderIntakeService;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\ProductService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
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

it('seeds one hundred realistic products with more than forty five live images', function (): void {
    $context = commerceContext();

    $this->seed(CommerceDemoSeeder::class);

    $products = Product::query()->where('workspace_id', $context['workspace']->id)->where('slug', 'like', 'demo-%')->get();
    $images = Media::query()->where('uploaded_by', $context['user']->id)->where('file_name', 'like', 'commerce-demo-%')->get();

    expect($products)->toHaveCount(100)
        ->and(ProductVariant::query()->where('workspace_id', $context['workspace']->id)->where('sku', 'like', 'DEMO-%')->count())->toBe(400)
        ->and($images)->toHaveCount(60)
        ->and($images->every(fn (Media $media): bool => str_starts_with($media->path, 'https://')))->toBeTrue()
        ->and($images->first()->url)->toBe($images->first()->path)
        ->and($products->every(fn (Product $product): bool => filled($product->brand_id) && filled($product->audience_id) && filled($product->primary_media_id)))->toBeTrue();

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

    $galleryResponse->assertRedirect(route('user.commerce.products.edit', ['product' => $product, 'step' => 3]));
    expect($product->fresh()->primary_media_id)->toBe($front->id)
        ->and($product->fresh()->wizard_step)->toBe(3)
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

it('generates a Meta CSV feed with garment attributes and USD prices', function (): void {
    $context = commerceContext();
    commerceProduct($context['workspace']->id);
    $catalog = Catalog::query()->create(['workspace_id' => $context['workspace']->id, 'channel_account_id' => $context['channel']->id, 'meta_catalog_id' => 'catalog-1', 'feed_token' => str_repeat('a', 64)]);

    $response = app(CatalogFeedService::class)->response($catalog);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('meta-jkt-blk-m')
        ->toContain('49.95 USD')
        ->toContain('Polyester')
        ->and($catalog->fresh()->last_item_count)->toBe(1);
});

it('includes additional gallery images in the Meta feed', function (): void {
    config(['app.url' => 'https://store.example.com', 'app.asset_url' => 'https://store.example.com']);
    $context = commerceContext();
    $product = commerceProduct($context['workspace']->id);
    $front = commerceMedia($context['user'], 'catalog-front');
    $back = commerceMedia($context['user'], 'catalog-back');
    app(ProductService::class)->updateGallery($product, [
        ['id' => $front->id, 'alt_text' => 'Front', 'is_primary' => true],
        ['id' => $back->id, 'alt_text' => 'Back', 'is_primary' => false],
    ]);
    $catalog = Catalog::query()->create(['workspace_id' => $context['workspace']->id, 'channel_account_id' => $context['channel']->id, 'meta_catalog_id' => 'catalog-gallery', 'feed_token' => str_repeat('g', 64)]);

    $response = app(CatalogFeedService::class)->response($catalog);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('additional_image_link')
        ->toContain('catalog-front.jpg')
        ->toContain('catalog-back.jpg');
});

it('queues idempotent direct catalog synchronization after capability checks pass', function (): void {
    Queue::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'catalog-api', 'name' => 'US Store'], 200)]);
    config(['app.url' => 'https://store.example.com', 'app.asset_url' => 'https://store.example.com']);
    URL::forceRootUrl('https://store.example.com');
    URL::forceScheme('https');
    $context = commerceContext();
    $context['channel']->update(['credentials' => ['access_token' => 'secret-token']]);
    $product = commerceProduct($context['workspace']->id);
    $front = commerceMedia($context['user'], 'api-front');
    app(ProductService::class)->updateGallery($product, [['id' => $front->id, 'alt_text' => 'Front', 'is_primary' => true]]);
    $catalog = Catalog::query()->create(['workspace_id' => $context['workspace']->id, 'channel_account_id' => $context['channel']->id, 'meta_catalog_id' => 'catalog-api', 'feed_token' => str_repeat('d', 64), 'sync_mode' => 'api']);

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
