<?php

use App\Models\User;
use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([NotifyOrderEvent::class]);
    $this->staff = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->staff);
    StoreOrderSetting::forWorkspace($this->workspace->id);
    $this->product = Product::query()->create([
        'workspace_id' => $this->workspace->id, 'name' => 'Snapshot shirt', 'slug' => 'shirt', 'sku' => 'shirt',
        'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => 10,
        'wholesale_price' => 6, 'ws_enabled' => true, 'ws_main_moq' => 3, 'ws_color_moq' => 3,
        'ws_min_sizes' => 3, 'ws_ratio_multiplier' => 1,
    ]);
    $this->color = $this->product->colors()->create(['workspace_id' => $this->workspace->id, 'name' => 'Red']);
    $this->product->update(['ws_size_ratios' => [$this->color->id => ['S' => 1, 'M' => 1, 'L' => 1]]]);
    $this->variants = collect(['S', 'M', 'L'])->map(fn ($size) => $this->product->variants()->create([
        'workspace_id' => $this->workspace->id, 'color_id' => $this->color->id, 'sku' => 'shirt-'.$size,
        'meta_retailer_id' => 'shirt-'.$size, 'size' => $size, 'price' => 10, 'stock_quantity' => 100, 'status' => 'active',
    ]));
    $this->createOrder = fn (array $groups) => app(UnifiedOrderService::class)->create($this->workspace, [
        'submission_reference' => (string) Str::uuid(), 'source' => 'manual',
        'customer' => ['name' => 'Test buyer', 'phone' => '+15555550100'],
        'shipping_address' => ['name' => 'Test buyer', 'phone' => '+15555550100', 'line1' => '1 Main Street', 'city' => 'Boston', 'country' => 'US'],
        'groups' => $groups,
    ]);
    $this->retail = ['product_id' => $this->product->id, 'mode' => 'retail', 'variant_id' => $this->variants[0]->id, 'quantity' => 2];
    $this->wholesale = ['product_id' => $this->product->id, 'mode' => 'wholesale', 'color_id' => $this->color->id, 'box_count' => 1];
    $this->image = fn ($name) => Media::query()->create([
        'name' => $name, 'file_name' => $name.'.png', 'original_name' => $name.'.png', 'extension' => 'png', 'type' => 'image', 'mime_type' => 'image/png',
        'disk' => 'public', 'path' => 'https://example.test/'.$name.'.png', 'size' => 100,
    ]);
    $this->actingAs($this->staff);
});

it('shows compact packs without codes prices or sku size fallbacks', function () {
    $order = ($this->createOrder)([$this->wholesale]);
    $order->items()->first()->update(['attributes' => ['color' => 'Red']]);
    $response = $this->get(route('user.commerce.orders.show', $order))->assertOk();
    $response->assertDontSee('Pack 1 of 1')->assertDontSee('Wholesale')->assertSee('Color: Red')->assertSee('No image')->assertDontSee('BOX-1')
        ->assertDontSee('shirt-S')->assertDontSee('Ordered items')->assertSee('USD 18.00');
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $xpath = new DOMXPath($document);
    $pack = $xpath->query('//*[@data-pack-card]')->item(0)->textContent;
    expect($pack)->not->toContain('USD')->toContain('—');
});

it('shows retail photos snapshot attributes and prices', function () {
    $image = ($this->image)('primary');
    $this->product->update(['primary_media_id' => $image->id]);
    $order = ($this->createOrder)([$this->retail]);
    $this->product->update(['name' => 'Changed catalog name']);
    $this->get(route('user.commerce.orders.show', $order))->assertOk()->assertSee('Ordered items')
        ->assertSee('Snapshot shirt')->assertDontSee('Changed catalog name')->assertSee('shirt-S')
        ->assertSee('Size:')->assertSee('Color: Red')->assertSee('USD 10.00')->assertSee('USD 20.00')
        ->assertSee($image->url)->assertSee('openProduct', false)->assertSee('order-product-gallery-title');
});

it('shows only remaining items alongside wholesale packs', function () {
    $order = ($this->createOrder)([$this->wholesale, $this->retail]);
    $response = $this->get(route('user.commerce.orders.show', $order))->assertOk()->assertSee('Packs')->assertSee('Ordered items');
    expect(substr_count($response->getContent(), 'data-order-item='))->toBe(1);
    $packedIds = $order->boxes->flatMap->contents->pluck('order_item_id');
    foreach ($packedIds as $id) {
        $response->assertDontSee('data-order-item="'.$id.'"', false);
    }
});

it('shows the remaining quantity when an item is partly boxed', function () {
    $order = ($this->createOrder)([$this->retail]);
    $item = $order->items->first();
    $box = $order->boxes()->create(['workspace_id' => $this->workspace->id, 'label' => 'Partial', 'kind' => 'retail']);
    $box->contents()->create(['order_item_id' => $item->id, 'quantity' => 1]);
    $response = $this->get(route('user.commerce.orders.show', $order))->assertOk()->assertSee('Ordered items');
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $xpath = new DOMXPath($document);
    expect(trim($xpath->query('//*[@data-order-item]//*[@data-item-quantity]')->item(0)->textContent))->toBe('1')
        ->and(trim($xpath->query('//*[@data-order-item]//*[@data-item-total]')->item(0)->textContent))->toBe('USD 10.00');
});

it('selects color photos and deduplicates urls without other colors', function () {
    $primary = ($this->image)('primary');
    $front = ($this->image)('red-front');
    $back = ($this->image)('red-back');
    $blue = $this->product->colors()->create(['workspace_id' => $this->workspace->id, 'name' => 'Blue']);
    $this->product->update(['primary_media_id' => $primary->id]);
    $this->color->update(['swatch_media_id' => $front->id]);
    foreach ([[$front, $this->color->id], [$back, $this->color->id], [($this->image)('blue'), $blue->id]] as $position => [$image, $colorId]) {
        $this->product->gallery()->create(['workspace_id' => $this->workspace->id, 'media_id' => $image->id, 'color_id' => $colorId, 'media_type' => 'image', 'position' => $position]);
    }
    $order = ($this->createOrder)([$this->retail]);
    expect(array_column($order->items->first()->productImages(), 'url'))->toBe([$front->url, $back->url])
        ->and($order->items->first()->productImages()[0]['label'])->toBe('Snapshot shirt · Red');
    $this->get(route('user.commerce.orders.show', $order))->assertOk()->assertSee($front->url)->assertSee('red-back.png')
        ->assertDontSee('https://example.test/blue.png')->assertDontSee($primary->url);
});

it('falls back to primary and general images while excluding videos', function () {
    $primary = ($this->image)('primary');
    $general = ($this->image)('general');
    $this->product->update(['primary_media_id' => $primary->id]);
    foreach ([[$general, 'image'], [($this->image)('video'), 'video']] as [$image, $type]) {
        $this->product->gallery()->create(['workspace_id' => $this->workspace->id, 'media_id' => $image->id, 'media_type' => $type]);
    }
    $order = ($this->createOrder)([$this->retail]);
    expect(array_column($order->items->first()->productImages(), 'url'))->toBe([$primary->url, $general->url]);
});

it('keeps snapshot details when the catalog variant is missing', function () {
    $order = ($this->createOrder)([$this->retail]);
    $order->items()->update(['variant_id' => null]);
    $this->get(route('user.commerce.orders.show', $order))->assertOk()->assertSee('Snapshot shirt')->assertSee('shirt-S')->assertSee('No image');
});

it('shows the product heading once for repeated packs and retail variants', function () {
    $wholesale = array_merge($this->wholesale, ['box_count' => 2]);
    $retailMedium = array_merge($this->retail, ['variant_id' => $this->variants[1]->id]);
    $order = ($this->createOrder)([$wholesale, $this->retail, $retailMedium]);
    $response = $this->get(route('user.commerce.orders.show', $order))->assertOk();
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-pack-product-group]/div/h3'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-pack-card]'))->toHaveCount(2)
        ->and($xpath->query('//*[@data-individual-product-group]/h3'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-order-item]'))->toHaveCount(2);
    $response->assertDontSee('Pack 1 of')->assertDontSee('Wholesale');
});

it('keeps product names visible inside mixed product packs', function () {
    $order = ($this->createOrder)([$this->wholesale]);
    $order->items()->first()->update(['product_name' => 'Second snapshot product']);
    $response = $this->get(route('user.commerce.orders.show', $order))->assertOk();
    $response->assertSee('Mixed products')->assertSee('Second snapshot product')->assertSee('Snapshot shirt');
});
