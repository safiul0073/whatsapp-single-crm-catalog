<?php

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductTierPrice;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartItem;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Models\PackTemplate;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\InvoiceService;
use App\Modules\Commerce\Storefront\Services\PackSelectionService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingRate;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'storefront.platform_secret' => str_repeat('p', 40), 'storefront.preview_secret' => str_repeat('v', 40), 'cache.default' => 'array']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Queue::fake();
    $this->store = Workspace::query()->create(['name' => 'One', 'slug' => 'one', 'status' => 'active']);
    $this->other = Workspace::query()->create(['name' => 'Two', 'slug' => 'two', 'status' => 'active']);
    foreach ([$this->store, $this->other] as $w) {
        StoreDomain::query()->create(['workspace_id' => $w->id, 'host' => $w->slug.'.localhost', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'test', 'type' => 'system']);
    }
    $this->headers = ['X-Storefront-Secret' => str_repeat('p', 40), 'X-Store-Host' => 'one.localhost'];
    $this->contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'Customer', 'phone' => '+8801700000000', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $this->customer = app(CustomerService::class)->enroll($this->contact, true);
    $this->auth = [...$this->headers, 'Authorization' => 'Bearer '.$this->customer->createToken('test', ['storefront'], now()->addDay())->plainTextToken];
    $this->product = Product::query()->create(['workspace_id' => $this->store->id, 'name' => 'Shirt', 'slug' => 'shirt', 'sku' => 'shirt', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20]);
    $this->variant = ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $this->product->id, 'sku' => 'shirt-m', 'meta_retailer_id' => 'shirt-m', 'size' => 'M', 'price' => 20, 'stock_quantity' => 500, 'status' => 'active']);
});
afterEach(function (): void {
    DB::disconnect('sqlite');
});

function mixedPackFixture($test): array
{
    $items = [];
    $ids = [];
    foreach (['Shirt', 'Trouser'] as $name) {
        $p = Product::query()->create(['workspace_id' => $test->store->id, 'name' => $name, 'slug' => strtolower($name).'-pack', 'sku' => strtolower($name).'-pack', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'ws_enabled' => true, 'single_piece_price' => 20, 'wholesale_price' => 10, 'ws_main_moq' => 100, 'ws_color_moq' => 10, 'ws_min_sizes' => 2]);
        $ids[] = $p->id;
        $color = ProductColor::query()->create(['workspace_id' => $test->store->id, 'product_id' => $p->id, 'name' => 'Black', 'slug' => 'black']);
        foreach (['S', 'M'] as $size) {
            $v = ProductVariant::query()->create(['workspace_id' => $test->store->id, 'product_id' => $p->id, 'color_id' => $color->id, 'sku' => $name.$size, 'meta_retailer_id' => $name.$size, 'size' => $size, 'price' => 20, 'stock_quantity' => 500, 'status' => 'active']);
            $items[] = ['variant_id' => $v->id, 'quantity' => 2];
        }
    }
    $t = PackTemplate::query()->create(['workspace_id' => $test->store->id, 'name' => 'Mixed starter', 'published' => true, 'product_ids' => $ids, 'min_pieces' => 8, 'min_products' => 2, 'min_colors' => 1, 'min_sizes' => 2, 'min_variant_quantity' => 2]);

    return ['type' => 'custom_pack', 'template_id' => $t->id, 'pack_count' => 1, 'items' => $items];
}

it('adds mixed packs atomically and preserves retail identity and retry keys', function () {
    $selection = mixedPackFixture($this);
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    $response = $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], $h)->assertOk()->assertJsonPath('data.subtotal', '80.00')->assertJsonPath('data.checkout_allowed', true);
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], $h)->assertOk()->assertJsonCount(1, 'data.groups');
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $selection['items'][0]['variant_id'], 'quantity' => 1], $h)->assertOk()->assertJsonPath('data.subtotal', '100.00')->assertJsonCount(2, 'data.groups');
    $this->patchJson('/api/storefront/v1/cart/items/'.$response->json('data.items.0.id'), ['quantity' => 99], $h)->assertUnprocessable();
    $bad = $selection;
    $bad['pack_count'] = 2;
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $bad], $h)->assertConflict();
    $this->deleteJson('/api/storefront/v1/cart/groups/'.$response->json('data.groups.0.id'), [], $h)->assertOk();
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], $h)->assertOk()->assertJsonCount(1, 'data.groups');
});

it('does not let pack count rescue invalid contents or mutate a rejected cart', function () {
    $selection = mixedPackFixture($this);
    $selection['items'][0]['quantity'] = 1;
    $selection['pack_count'] = 100;
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], $h)->assertUnprocessable();
    $this->getJson('/api/storefront/v1/cart', $h)->assertJsonCount(0, 'data.groups')->assertJsonCount(0, 'data.items');
});

it('revalidates rules and combined stock without downgrading packs', function () {
    $selection = mixedPackFixture($this);
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], $h)->assertOk();
    PackTemplate::query()->find($selection['template_id'])->update(['min_colors' => 2]);
    $this->getJson('/api/storefront/v1/cart', $h)->assertOk()->assertJsonPath('data.checkout_allowed', false)->assertJsonPath('data.items.0.mode', 'wholesale');
    $this->getJson('/api/storefront/v1/pack-templates', [...$this->headers, 'X-Store-Host' => 'two.localhost'])->assertJsonCount(0, 'data');
});

it('enforces each minimum boundary and rejects tampered prices, foreign templates, and ineligible variants', function () {
    $selection = mixedPackFixture($this);
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token];

    // 1. Foreign workspace template
    $otherTemplate = PackTemplate::query()->create([
        'workspace_id' => $this->other->id,
        'name' => 'Foreign Template',
        'published' => true,
        'product_ids' => [$this->product->id],
        'min_pieces' => 1,
    ]);
    $foreign = $selection;
    $foreign['template_id'] = $otherTemplate->id;
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $foreign], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();

    // 2. Tampered price
    $tampered = $selection;
    $tampered['price'] = 1.00;
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $tampered], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();

    // 3. Retail-only variant in custom pack
    $ineligible = $selection;
    $ineligible['items'][0]['variant_id'] = $this->variant->id; // this->variant belongs to retail-only product
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $ineligible], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();

    // 4. Min variant quantity boundary
    $template = PackTemplate::query()->find($selection['template_id']);
    $template->update(['min_variant_quantity' => 5]);
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
    $template->update(['min_variant_quantity' => 2]);

    // 5. Min distinct products boundary
    $template->update(['min_products' => 3]);
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
    $template->update(['min_products' => 2]);

    // 6. Max pieces boundary
    $template->update(['max_pieces' => 4]); // selection has 8 pieces
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
    $template->update(['max_pieces' => null]);
});

it('applies tier pricing based on total wholesale quantity across groups excluding retail', function () {
    $selection = mixedPackFixture($this);
    // Add a wholesale tier to Shirt: 10+ pieces -> wholesale price $6.00 (base wholesale is $10.00)
    $shirtId = $selection['items'][0]['variant_id'];
    $shirtVariant = ProductVariant::query()->with('product')->find($shirtId);
    $shirtProduct = $shirtVariant->product;
    ProductTierPrice::query()->create([
        'workspace_id' => $this->store->id,
        'product_id' => $shirtProduct->id,
        'min_quantity' => 8,
        'max_quantity' => null,
        'unit_price' => 6.00,
    ]);

    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token];

    // First add retail shirt: 5 pieces @ retail price $20.00
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $shirtVariant->id, 'quantity' => 5], $h)->assertOk();

    // Now add mixed pack with pack_count = 2 (Shirt has 2 variants x 2 qty x 2 packs = 8 pieces of Shirt wholesale!)
    $selection['pack_count'] = 2;
    $res = $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertOk();

    // The retail shirt item should remain at $20.00
    $retailItem = collect($res->json('data.items'))->firstWhere('mode', 'retail');
    expect($retailItem['price'])->toBe('20.00');

    // The wholesale shirt items should qualify for tier price $6.00 (since 8 wholesale pieces are present)
    $wsShirtItem = collect($res->json('data.items'))->first(fn ($i) => $i['mode'] === 'wholesale' && $i['product_id'] === $shirtProduct->id);
    expect($wsShirtItem['price'])->toBe('6.00');
});

it('migrates legacy unconfirmed cart items to retail groups via confirm-retail', function () {
    $cart = Cart::query()->create([
        'workspace_id' => $this->store->id,
        'token_hash' => hash('sha256', 'legacy-cart-token'),
        'currency' => 'USD',
        'expires_at' => now()->addDays(30),
    ]);
    // Insert a legacy line item with group_id = null
    $legacyItem = CartItem::query()->create([
        'workspace_id' => $this->store->id,
        'cart_id' => $cart->id,
        'product_id' => $this->product->id,
        'variant_id' => $this->variant->id,
        'quantity' => 2,
        'price_snapshot' => 20.00,
        'group_id' => null,
    ]);

    $h = [...$this->headers, 'X-Cart-Token' => 'legacy-cart-token'];
    $summary = $this->getJson('/api/storefront/v1/cart', $h)->assertOk()->json('data');
    expect($summary['checkout_allowed'])->toBeFalse()
        ->and($summary['items'][0]['mode'])->toBe('unconfirmed');

    // Confirm as retail
    $confirmed = $this->postJson('/api/storefront/v1/cart/items/'.$legacyItem->id.'/confirm-retail', [], $h)->assertOk()->json('data');
    expect($confirmed['checkout_allowed'])->toBeTrue()
        ->and($confirmed['items'][0]['mode'])->toBe('retail')
        ->and($confirmed['groups'])->toHaveCount(1)
        ->and($confirmed['groups'][0]['type'])->toBe('retail');
});

it('creates order with custom pack snapshot, shared packing box, and invoice formatting', function () {
    $selection = mixedPackFixture($this);
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->auth)->json('data.cart_token');
    $h = [...$this->auth, 'X-Cart-Token' => $token];

    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertOk();

    // Configure workspace payment methods & shipping zone
    $this->store->update(['settings' => ['commerce' => ['currency' => 'USD', 'payment_methods' => [['id' => 'bank', 'name' => 'Bank transfer', 'active' => '1', 'recipient_details' => 'Test account', 'instructions' => 'Transfer and upload proof', 'sort_order' => 0, 'fields' => []]]]]]);
    $zone = ShippingZone::query()->create(['workspace_id' => $this->store->id, 'name' => 'Bangladesh', 'code' => 'BD', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $this->store->id, 'country_code' => 'BD']);
    $method = ShippingMethod::query()->create(['workspace_id' => $this->store->id, 'name' => 'Delivery', 'code' => 'standard', 'type' => 'standard', 'is_active' => true]);
    ShippingRate::query()->create(['workspace_id' => $this->store->id, 'shipping_zone_id' => $zone->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'max_weight_kg' => 100, 'price' => 5, 'price_per_kg' => 0, 'currency' => 'USD', 'is_active' => true]);

    $addr = CustomerAddress::query()->create([
        'workspace_id' => $this->store->id,
        'customer_id' => $this->customer->id,
        'label' => 'Home',
        'address' => [
            'name' => 'Jane Doe',
            'line1' => '123 Market St',
            'city' => 'Dhaka',
            'country' => 'BD',
            'phone' => '+8801700000000',
        ],
    ]);

    $orderRes = $this->postJson('/api/storefront/v1/checkout/orders', [
        'address_id' => $addr->id,
        'shipping_method_id' => $method->id,
        'payment_method_id' => 'bank',
    ], [...$this->auth, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $orderId = $orderRes->json('data.id');
    $order = Order::query()->with(['groups', 'boxes.contents.item'])->findOrFail($orderId);

    // Verify order group has pack_snapshot
    expect($order->groups->first()->pack_snapshot)->not->toBeNull()
        ->and($order->groups->first()->pack_snapshot['name'])->toBe('Mixed starter');

    // Verify packing boxes span the products in the custom pack
    expect($order->boxes)->not->toBeEmpty();
    $boxContents = $order->boxes->first()->contents;
    $distinctProducts = $boxContents->map(fn ($c) => $c->item->product_name)->unique();
    expect($distinctProducts->count())->toBe(2);

    // Verify PDF invoice generation formats the custom pack without errors
    $pdf = app(InvoiceService::class)->pdf($order);
    expect($pdf)->toContain('%PDF-1.4')
        ->and($pdf)->toContain('Mixed starter');
});

it('counts 100 mixed packs with multiple colors as 100 physical boxes and rejects genuine overflow', function () {
    // Create 2 products with 3 colors each = 6 color groups
    $items = [];
    $ids = [];
    foreach (['Jacket', 'Pants'] as $name) {
        $p = Product::query()->create(['workspace_id' => $this->store->id, 'name' => $name, 'slug' => strtolower($name).'-box-test', 'sku' => strtolower($name).'-box-test', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'wholesale', 'ws_enabled' => true, 'single_piece_price' => 50, 'wholesale_price' => 25, 'ws_main_moq' => 10, 'ws_color_moq' => 5, 'ws_min_sizes' => 1]);
        $ids[] = $p->id;
        foreach (['Red', 'Green', 'Blue'] as $colorName) {
            $color = ProductColor::query()->create(['workspace_id' => $this->store->id, 'product_id' => $p->id, 'name' => $colorName, 'slug' => strtolower($name.'-'.$colorName)]);
            $v = ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $p->id, 'color_id' => $color->id, 'sku' => $name.$colorName.'M', 'meta_retailer_id' => $name.$colorName.'M', 'size' => 'M', 'price' => 25, 'stock_quantity' => 10000, 'status' => 'active']);
            $items[] = ['variant_id' => $v->id, 'quantity' => 1];
        }
    }
    $t = PackTemplate::query()->create(['workspace_id' => $this->store->id, 'name' => '6-color mix', 'published' => true, 'product_ids' => $ids, 'min_pieces' => 6, 'min_products' => 2, 'min_colors' => 3, 'min_sizes' => 1, 'min_variant_quantity' => 1]);

    $selection = ['type' => 'custom_pack', 'template_id' => $t->id, 'pack_count' => 100, 'items' => $items];

    $packService = app(PackSelectionService::class);
    $validated = $packService->validate($this->store->id, $selection);
    // 6 order groups are created (2 products x 3 colors)
    expect($validated['orders'])->toHaveCount(6);

    // Box count MUST be 100, NOT 600!
    $boxCount = $packService->physicalBoxCount($validated['orders']);
    expect($boxCount)->toBe(100);

    // Adding 100 packs to cart should succeed and allow checkout
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    $cartRes = $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], $h)->assertOk();
    expect($cartRes->json('data.checkout_allowed'))->toBeTrue();

    // 501 packs exceeds 500-box limit
    $selectionOverflow = $selection;
    $selectionOverflow['pack_count'] = 501;
    expect(fn () => $packService->validate($this->store->id, $selectionOverflow))->toThrow(ValidationException::class);
});

it('releases all constituent inventory upon cancellation', function () {
    $selection = mixedPackFixture($this);
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->auth)->json('data.cart_token');
    $h = [...$this->auth, 'X-Cart-Token' => $token];

    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertOk();

    $this->store->update(['settings' => ['commerce' => ['currency' => 'USD', 'payment_methods' => [['id' => 'bank', 'name' => 'Bank transfer', 'active' => '1', 'recipient_details' => 'Test account', 'instructions' => 'Transfer and upload proof', 'sort_order' => 0, 'fields' => []]]]]]);
    $zone = ShippingZone::query()->create(['workspace_id' => $this->store->id, 'name' => 'BD Zone', 'code' => 'BDZ', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $this->store->id, 'country_code' => 'BD']);
    $method = ShippingMethod::query()->create(['workspace_id' => $this->store->id, 'name' => 'Standard', 'code' => 'std', 'type' => 'standard', 'is_active' => true]);
    ShippingRate::query()->create(['workspace_id' => $this->store->id, 'shipping_zone_id' => $zone->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'max_weight_kg' => 100, 'price' => 5, 'price_per_kg' => 0, 'currency' => 'USD', 'is_active' => true]);

    $addr = CustomerAddress::query()->create([
        'workspace_id' => $this->store->id,
        'customer_id' => $this->customer->id,
        'label' => 'Office',
        'address' => ['name' => 'Jane', 'line1' => '123 Market', 'city' => 'Dhaka', 'country' => 'BD', 'phone' => '+8801700000000'],
    ]);

    $orderRes = $this->postJson('/api/storefront/v1/checkout/orders', [
        'address_id' => $addr->id,
        'shipping_method_id' => $method->id,
        'payment_method_id' => 'bank',
    ], [...$this->auth, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $order = Order::query()->findOrFail($orderRes->json('data.id'));
    // Reservations are held
    $variantIds = collect($selection['items'])->pluck('variant_id')->all();
    $initialReservations = OrderReservation::query()->where('order_id', $order->id)->get();
    expect($initialReservations)->toHaveCount(count($variantIds));
    foreach ($initialReservations as $r) {
        expect($r->state)->toBe('reserved');
    }

    // Cancel order
    $workflow = app(OrderWorkflowService::class);
    $workflow->transition($order, 'cancelled');

    // All reservations must be released
    $cancelledReservations = OrderReservation::query()->where('order_id', $order->id)->get();
    foreach ($cancelledReservations as $r) {
        expect($r->state)->toBe('released');
    }
});

it('provides non-persistent preview with explicit group data and validates replace_group_id', function () {
    $selection = mixedPackFixture($this);
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token];

    // Preview non-existing replace_group_id
    $this->postJson('/api/storefront/v1/cart/groups/preview', [
        'selection' => $selection,
        'replace_group_id' => 99999,
    ], $h)->assertStatus(422);

    // Valid preview
    $res = $this->postJson('/api/storefront/v1/cart/groups/preview', [
        'selection' => $selection,
    ], $h)->assertOk();

    // Verify explicit previewed group data and cart totals
    expect($res->json('data.group.price'))->toBe('80.00')
        ->and($res->json('data.group.total_pieces'))->toBe(8)
        ->and($res->json('data.subtotal'))->toBe('80.00')
        ->and($res->json('data.checkout_allowed'))->toBeTrue();

    // Verify non-persistence: cart database still has 0 items and 0 groups
    $this->getJson('/api/storefront/v1/cart', $h)->assertOk()
        ->assertJsonCount(0, 'data.groups')
        ->assertJsonCount(0, 'data.items');
});

it('sells retail only when the owner switches wholesale off', function () {
    $selection = mixedPackFixture($this);
    $wholesaleOnly = Product::query()->create(['workspace_id' => $this->store->id, 'name' => 'Bulk only', 'slug' => 'bulk-only', 'sku' => 'bulk-only', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'wholesale', 'ws_enabled' => true, 'wholesale_price' => 5]);
    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)->assertOk()->assertJsonPath('data.wholesale_enabled', true);

    StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->store->id], ['config' => ['wholesale_enabled' => false]]);
    StoreFeatures::flush();

    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)->assertOk()->assertJsonPath('data.wholesale_enabled', false);
    $this->getJson('/api/storefront/v1/pack-templates', $this->headers)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/storefront/v1/catalog/products/'.$wholesaleOnly->slug, $this->headers)->assertNotFound();
    $this->getJson('/api/storefront/v1/catalog/products?selling_mode=wholesale', $this->headers)->assertOk()->assertJsonMissing(['slug' => 'bulk-only']);
    $this->getJson('/api/storefront/v1/catalog/products/shirt-pack', $this->headers)->assertOk()
        ->assertJsonPath('data.selling_mode', 'retail')
        ->assertJsonPath('data.ws_enabled', false)
        ->assertJsonPath('data.wholesale_price', '0')
        ->assertJsonCount(0, 'data.tier_prices');

    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $selection], $h)->assertUnprocessable();
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $selection['items'][0]['variant_id'], 'quantity' => 1], $h)->assertOk()->assertJsonPath('data.subtotal', '20.00');
});

it('prices pack-mode standard wholesale from the pack price list and skips shipping for wholesale-only carts', function () {
    $p = Product::query()->create(['workspace_id' => $this->store->id, 'name' => 'Set', 'slug' => 'set', 'sku' => 'set', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'ws_enabled' => true, 'single_piece_price' => 20, 'wholesale_price' => 10, 'ws_main_moq' => 1, 'ws_color_moq' => 1, 'ws_moq_unit' => 'packs', 'ws_pack_prices' => [['packs' => 2, 'price' => '500'], ['packs' => 3, 'price' => '600']]]);
    $colors = [];
    foreach (['Red', 'Blue'] as $name) {
        $color = ProductColor::query()->create(['workspace_id' => $this->store->id, 'product_id' => $p->id, 'name' => $name, 'slug' => strtolower($name)]);
        $colors[] = $color->id;
        foreach (['S', 'M', 'L'] as $size) {
            ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $p->id, 'color_id' => $color->id, 'sku' => $name.$size, 'meta_retailer_id' => $name.$size, 'size' => $size, 'price' => 20, 'stock_quantity' => 500, 'status' => 'active']);
        }
        $ratios[$color->id] = ['S' => 2, 'M' => 2, 'L' => 2];
    }
    $p->update(['ws_size_ratios' => $ratios]);
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token];

    $oneColor = ['type' => 'standard_wholesale', 'product_id' => $p->id, 'colors' => [['color_id' => $colors[0], 'box_count' => 1]]];
    $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $oneColor], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();

    $twoColors = ['type' => 'standard_wholesale', 'product_id' => $p->id, 'colors' => [['color_id' => $colors[0], 'box_count' => 1], ['color_id' => $colors[1], 'box_count' => 1]]];
    $res = $this->postJson('/api/storefront/v1/cart/groups', ['selection' => $twoColors], [...$h, 'Idempotency-Key' => (string) Str::uuid()])->assertOk();
    expect($res->json('data.subtotal'))->toBe('500.00')->and($res->json('data.shipping_required'))->toBeFalse();
    $this->getJson('/api/storefront/v1/catalog/products/set', $this->headers)->assertJsonPath('data.wholesale_settings.moq_unit', 'packs')->assertJsonPath('data.wholesale_settings.min_packs', 2);

    $res = $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 1], $h)->assertOk();
    expect($res->json('data.subtotal'))->toBe('520.00')->and($res->json('data.shipping_required'))->toBeTrue();
});

it('lets only customers with a completed order of the product rate it', function () {
    $url = '/api/storefront/v1/catalog/products/shirt/reviews';
    $this->getJson('/api/storefront/v1/catalog/products/shirt/review-eligibility', $this->headers)->assertUnauthorized();
    $this->getJson('/api/storefront/v1/catalog/products/shirt/review-eligibility', $this->auth)->assertOk()->assertJsonPath('data.can_review', false);
    $this->postJson($url, ['rating' => 5, 'body' => 'Great'], $this->auth)->assertForbidden();

    $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => $this->contact->id, 'source' => 'storefront', 'submission_reference' => (string) Str::uuid(), 'payload_hash' => 'x', 'number' => 'ORD-R1', 'tracking_code' => 'TRK-R1', 'status' => 'processing', 'currency' => 'USD', 'subtotal' => 20, 'total' => 20, 'customer_snapshot' => [], 'shipping_address' => []]);
    $order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $this->variant->id, 'retailer_id' => 'shirt-m', 'sku' => 'shirt-m', 'product_name' => 'Shirt', 'quantity' => 1, 'unit_price' => 20, 'line_total' => 20]);
    $this->postJson($url, ['rating' => 5, 'body' => 'Great'], $this->auth)->assertForbidden();

    $order->update(['status' => 'completed']);
    $this->getJson('/api/storefront/v1/catalog/products/shirt/review-eligibility', $this->auth)->assertOk()->assertJsonPath('data.can_review', true);
    $this->postJson($url, ['rating' => 4, 'body' => 'Great'], $this->auth)->assertCreated()->assertJsonPath('data.status', 'pending');
});
