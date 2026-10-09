<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\ProductShowcaseLink;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create());
    config(['commerce.store_workspace_id' => $workspace->id, 'commerce.showcase_frontend_url' => 'https://store.example.test']);
    StoreOrderSetting::forWorkspace($workspace->id);
    $product = Product::query()->create(['workspace_id' => $workspace->id, 'name' => 'Showcase shirt', 'slug' => 'showcase-shirt', 'sku' => 'SHOWCASE', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => 10, 'wholesale_price' => 6, 'default_unit_weight_kg' => 1, 'ws_enabled' => true, 'ws_main_moq' => 6, 'ws_color_moq' => 3, 'ws_min_sizes' => 2, 'ws_ratio_multiplier' => 1]);
    $color = $product->colors()->create(['workspace_id' => $workspace->id, 'name' => 'Red', 'hex_code' => '#ff0000']);
    $product->update(['ws_size_ratios' => [$color->id => ['S' => 1, 'M' => 2]]]);
    $variants = collect(['S', 'M'])->map(fn ($size) => $product->variants()->create(['workspace_id' => $workspace->id, 'color_id' => $color->id, 'sku' => 'SHOW-'.$size, 'meta_retailer_id' => 'SHOW-'.$size, 'size' => $size, 'price' => 10, 'cost_price' => 3, 'stock_quantity' => 20, 'status' => 'active']));
    $zone = ShippingZone::create(['workspace_id' => $workspace->id, 'name' => 'USA', 'code' => 'US', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $workspace->id, 'country_code' => 'US']);
    $method = ShippingMethod::create(['workspace_id' => $workspace->id, 'name' => 'Air', 'code' => 'AIR', 'type' => 'air', 'is_active' => true]);
    $rate = $zone->rates()->create(['workspace_id' => $workspace->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'price' => 5, 'price_per_kg' => 2, 'currency' => 'USD', 'is_active' => true]);
    $this->showcase = compact('workspace', 'product', 'color', 'variants', 'zone', 'method', 'rate');
    $this->endpoint = '/api/commerce/store/showcase/showcase-shirt';
});

function showcaseRetail(array $context, int $quantity = 2): array
{
    return ['country' => 'US', 'groups' => [['mode' => 'retail', 'variant_id' => $context['variants'][0]->id, 'quantity' => $quantity]]];
}

it('serves anonymous live availability without private costs', function () {
    $this->getJson($this->endpoint)->assertOk()->assertJsonPath('data.total_available', 40)
        ->assertJsonCount(2, 'data.variants')->assertJsonMissingPath('data.variants.0.cost_price')
        ->assertJsonPath('data.gallery', [])->assertHeader('Cache-Control', 'no-store, private');
    $this->showcase['variants'][0]->update(['status' => 'out_of_stock']);
    $this->getJson($this->endpoint)->assertOk()->assertJsonPath('data.total_available', 20)->assertJsonPath('data.variants.0.available', 0);
    $this->showcase['variants'][1]->update(['status' => 'inactive']);
    $this->getJson($this->endpoint)->assertOk()->assertJsonCount(1, 'data.variants')->assertJsonPath('data.total_available', 0);
});

it('rejects unpublished inactive and disabled shop showcases', function (string $field, mixed $value) {
    if ($field === 'shop') {
        $this->showcase['workspace']->update(['settings' => ['commerce' => ['shop_enabled' => false]]]);
    } else {
        $this->showcase['product']->update([$field => $value]);
    }
    $this->getJson($this->endpoint)->assertNotFound();
    $this->postJson($this->endpoint.'/estimate', showcaseRetail($this->showcase))->assertNotFound();
})->with([['visibility', 'draft'], ['status', 'inactive'], ['shop', false]]);

it('isolates the configured workspace and does not accept foreign variants', function () {
    $other = app(WorkspaceResolver::class)->current(User::factory()->create());
    $foreign = Product::create(['workspace_id' => $other->id, 'name' => 'Foreign', 'slug' => 'foreign', 'status' => 'active', 'visibility' => 'published']);
    $variant = $foreign->variants()->create(['workspace_id' => $other->id, 'sku' => 'FOREIGN', 'meta_retailer_id' => 'FOREIGN', 'status' => 'active', 'stock_quantity' => 50, 'price' => 1]);
    $this->getJson('/api/commerce/store/showcase/foreign')->assertNotFound();
    $data = showcaseRetail($this->showcase);
    $data['groups'][0]['variant_id'] = $variant->id;
    $this->postJson($this->endpoint.'/estimate', $data)->assertUnprocessable();
});

it('calculates mixed retail quantities with server prices and shipping without creating orders', function () {
    $data = showcaseRetail($this->showcase);
    $data['groups'][] = ['mode' => 'retail', 'variant_id' => $this->showcase['variants'][1]->id, 'quantity' => 3];
    $this->postJson($this->endpoint.'/estimate', $data)->assertOk()->assertJsonPath('data.subtotal', '50.00')
        ->assertJsonPath('data.shipping_amount', '15.00')->assertJsonPath('data.total', '65.00')->assertJsonPath('product.total_available', 40);
    expect(Order::count())->toBe(0)->and(OrderReservation::count())->toBe(0);
});

it('uses wholesale boxes and quantity tiers and enforces minimums', function () {
    $c = $this->showcase;
    $c['product']->tierPrices()->create(['workspace_id' => $c['workspace']->id, 'min_quantity' => 6, 'unit_price' => 4]);
    $data = ['country' => 'US', 'groups' => [['mode' => 'wholesale', 'color_id' => $c['color']->id, 'box_count' => 2]]];
    $response = $this->postJson($this->endpoint.'/estimate', $data)->assertOk()->assertJsonPath('data.subtotal', '24.00')
        ->assertJsonPath('data.groups.0.quantity', 6);
    expect(collect($response->json('data.groups.0.items'))->firstWhere('variant_id', $c['variants'][1]->id)['quantity'])->toBe(4);
    $data['groups'][0]['box_count'] = 1;
    $this->postJson($this->endpoint.'/estimate', $data)->assertUnprocessable();
    $data['groups'][0]['box_count'] = 11;
    $this->postJson($this->endpoint.'/estimate', $data)->assertUnprocessable()->assertJsonPath('product.total_available', 40);
});

it('rejects tampered or invalid selections', function (array $changes) {
    $data = showcaseRetail($this->showcase);
    $data['groups'][0] = array_merge($data['groups'][0], $changes);
    $this->postJson($this->endpoint.'/estimate', $data)->assertUnprocessable();
})->with([[['price' => 0]], [['product_id' => 999]], [['ratio' => ['M' => 1]]], [['quantity' => -1]], [['quantity' => 1.5]], [['quantity' => 21]]]);

it('subtracts active reservations and rejects aggregate quantities exceeding remaining stock', function () {
    $c = $this->showcase;
    $data = ['submission_reference' => (string) Str::uuid(), 'source' => 'manual', 'customer' => ['name' => 'Buyer', 'phone' => '+15555550100'], 'shipping_address' => ['name' => 'Buyer', 'phone' => '+15555550100', 'line1' => 'Street', 'city' => 'Boston', 'country' => 'US'], 'groups' => [['product_id' => $c['product']->id, 'mode' => 'retail', 'variant_id' => $c['variants'][0]->id, 'quantity' => 5]]];
    $order = app(UnifiedOrderService::class)->create($c['workspace'], $data);
    $this->getJson($this->endpoint)->assertOk()->assertJsonPath('data.variants.0.available', 15);
    $request = showcaseRetail($c, 8);
    $request['groups'][] = $request['groups'][0];
    $this->postJson($this->endpoint.'/estimate', $request)->assertUnprocessable()->assertJsonPath('product.total_available', 35);
    $order->reservations()->update(['expires_at' => now()->subMinute()]);
    $this->getJson($this->endpoint)->assertOk()->assertJsonPath('data.total_available', 40);
});

it('does not imply free shipping for missing or different-currency rates', function (string $currency, string $country) {
    $this->showcase['rate']->update(['currency' => $currency]);
    $data = showcaseRetail($this->showcase);
    $data['country'] = $country;
    $this->postJson($this->endpoint.'/estimate', $data)->assertOk()->assertJsonPath('data.shipping_quote_required', true)->assertJsonPath('data.shipping_amount', null)->assertJsonPath('data.total', null);
})->with([['EUR', 'US'], ['USD', 'ZZ']]);

it('validates the selected shipping method', function () {
    $data = showcaseRetail($this->showcase);
    $data['shipping_method_id'] = 99999;
    $this->postJson($this->endpoint.'/estimate', $data)->assertUnprocessable()->assertJsonValidationErrors('shipping_method_id');
    $data['shipping_method_id'] = $this->showcase['method']->id;
    $this->postJson($this->endpoint.'/estimate', $data)->assertOk()->assertJsonPath('data.shipping_method_id', $this->showcase['method']->id);
});

it('generates only configured frontend showcase links for publishable products', function () {
    $links = app(ProductShowcaseLink::class);
    $product = $this->showcase['product'];
    expect($links->url($product))->toBe('https://store.example.test/share/product/showcase-shirt');
    $html = view('commerce::user.partials.product-showcase', compact('product'))->render();
    expect($html)->toContain('Copy showcase link', 'https://store.example.test/share/product/showcase-shirt');
    config(['commerce.showcase_frontend_url' => null]);
    expect($links->url($product))->toBeNull();
    expect(view('commerce::user.partials.product-showcase', compact('product'))->render())->toContain('Configure the Ecommarce frontend URL');
    config(['commerce.showcase_frontend_url' => 'javascript:alert(1)']);
    expect($links->url($product))->toBeNull();
});

it('estimates retail pieces and wholesale boxes together in one request', function () {
    $context = $this->showcase;
    $response = $this->postJson($this->endpoint.'/estimate', ['country' => 'US', 'groups' => [
        ['mode' => 'retail', 'variant_id' => $context['variants'][0]->id, 'quantity' => 2],
        ['mode' => 'wholesale', 'color_id' => $context['color']->id, 'box_count' => 2],
    ]])->assertOk();

    expect(collect($response->json('data.groups'))->pluck('mode')->all())->toBe(['retail', 'wholesale'])
        ->and(Order::query()->count())->toBe(0)
        ->and(OrderReservation::query()->count())->toBe(0);
});

it('shows showcase actions on the product list and the product edit page', function () {
    $user = $this->showcase['workspace']->owner;
    Permission::findOrCreate('commerce.manage', 'web');
    Permission::findOrCreate('commerce.view', 'web');
    $user->givePermissionTo(['commerce.manage', 'commerce.view']);
    $url = 'https://store.example.test/share/product/showcase-shirt';

    $this->actingAs($user)->get(route('user.commerce.products.edit', $this->showcase['product']))
        ->assertOk()->assertSee('Copy showcase link')->assertSee($url, false);
    $this->actingAs($user)->get(route('user.commerce.products.index'))
        ->assertOk()->assertSee('Open showcase')->assertSee($url, false);
});

it('shows customer friendly color names without hex codes', function () {
    $this->showcase['color']->update(['name' => 'Black (#0A0A0A)', 'hex_code' => '#0A0A0A']);

    $this->getJson($this->endpoint)->assertOk()
        ->assertJsonPath('data.colors.0.name', 'Black')
        ->assertJsonPath('data.colors.0.hex', '#0A0A0A');
});

it('quotes the value of all available stock with shipping for a destination', function () {
    $response = $this->getJson($this->endpoint.'/stock-quote?country=US')->assertOk()
        ->assertJsonPath('data.pieces', 40)
        ->assertJsonPath('data.shipping_quote_required', false)
        ->assertJsonPath('data.shipping_method_id', $this->showcase['method']->id)
        ->assertJsonPath('data.weight_kg', 40);

    expect((float) $response->json('data.subtotal'))->toBe(400.0)
        ->and((float) $response->json('data.total'))->toBe((float) $response->json('data.subtotal') + (float) $response->json('data.shipping_amount'))
        ->and(Order::query()->count())->toBe(0)
        ->and(OrderReservation::query()->count())->toBe(0);
});

it('reports unavailable shipping for stock quotes without a rate', function () {
    $this->getJson($this->endpoint.'/stock-quote?country=DE')->assertOk()
        ->assertJsonPath('data.shipping_quote_required', true)
        ->assertJsonPath('data.total', null)
        ->assertJsonPath('data.shipping_amount', null);

    $this->getJson($this->endpoint.'/stock-quote?country=usa')->assertUnprocessable();
});

it('filters the public product list to wholesale-capable products when asked', function (): void {
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create());
    foreach (['retail', 'wholesale', 'both'] as $mode) {
        Product::create(['workspace_id' => $workspace->id, 'name' => "Item {$mode}", 'slug' => "item-{$mode}", 'sku' => strtoupper($mode), 'status' => 'active', 'visibility' => 'published', 'selling_mode' => $mode, 'single_piece_price' => 10]);
    }

    $listedSlugs = fn (string $query) => collect($this->getJson('/api/commerce/products'.$query)->json('data'))->pluck('slug')->all();

    expect($listedSlugs(''))->toContain('item-retail', 'item-wholesale', 'item-both')
        ->and($listedSlugs('?selling_mode=wholesale'))->toContain('item-wholesale', 'item-both')->not->toContain('item-retail');
});
