<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Models\OrderEditRequest;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\OrderEditRequestService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingRate;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'storefront.platform_secret' => str_repeat('p', 40), 'storefront.preview_secret' => str_repeat('v', 40), 'cache.default' => 'array']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Queue::fake();
    $this->store = Workspace::query()->create(['name' => 'One', 'slug' => 'one', 'status' => 'active']);
    StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'one.localhost', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'test', 'type' => 'system']);
    $this->headers = ['X-Storefront-Secret' => str_repeat('p', 40), 'X-Store-Host' => 'one.localhost'];
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'Customer', 'phone' => '+8801700000000', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $this->customer = app(CustomerService::class)->enroll($contact, true);
    $this->auth = [...$this->headers, 'Authorization' => 'Bearer '.$this->customer->createToken('test', ['storefront'], now()->addDay())->plainTextToken];
    $this->product = Product::query()->create(['workspace_id' => $this->store->id, 'name' => 'Shirt', 'slug' => 'shirt', 'sku' => 'shirt', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20]);
    $this->variant = ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $this->product->id, 'sku' => 'shirt-m', 'meta_retailer_id' => 'shirt-m', 'size' => 'M', 'price' => 20, 'stock_quantity' => 5, 'status' => 'active']);
    $this->staff = (new User)->forceFill(['id' => DB::table('users')->insertGetId(['name' => 'Owner'])]);

    $this->store->update(['settings' => ['commerce' => ['currency' => 'USD', 'payment_methods' => [['id' => 'bank', 'name' => 'Bank transfer', 'active' => '1', 'recipient_details' => 'Test bank account', 'instructions' => 'Transfer and upload proof', 'sort_order' => 0, 'fields' => []]]]]]);
    $zone = ShippingZone::query()->create(['workspace_id' => $this->store->id, 'name' => 'Bangladesh', 'code' => 'BD', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $this->store->id, 'country_code' => 'BD']);
    $method = ShippingMethod::query()->create(['workspace_id' => $this->store->id, 'name' => 'Delivery', 'code' => 'standard', 'type' => 'standard', 'is_active' => true]);
    ShippingRate::query()->create(['workspace_id' => $this->store->id, 'shipping_zone_id' => $zone->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'max_weight_kg' => 100, 'price' => 5, 'price_per_kg' => 0, 'currency' => 'USD', 'is_active' => true]);
    $address = CustomerAddress::query()->create(['workspace_id' => $this->store->id, 'customer_id' => $this->customer->id, 'label' => 'Home', 'address' => ['name' => 'Customer', 'phone' => '+8801700000000', 'line1' => '123 Test road', 'city' => 'Dhaka', 'country' => 'BD']]);
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->auth)->assertCreated()->json('data.cart_token');
    $cart = [...$this->auth, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 2], $cart)->assertOk();
    $ref = $this->postJson('/api/storefront/v1/checkout/orders', ['address_id' => $address->id, 'shipping_method_id' => $method->id, 'payment_method_id' => 'bank'], $cart)->assertCreated()->json('data.submission_reference');
    $this->order = Order::query()->where('submission_reference', $ref)->firstOrFail();
    $this->ref = $ref;
    $this->url = fn (string $path = ''): string => '/api/storefront/v1/me/orders/'.$this->ref.$path;
    $this->retail = fn (int $quantity): array => ['groups' => [['product_id' => $this->product->id, 'mode' => 'retail', 'variant_id' => $this->variant->id, 'quantity' => $quantity]]];
    $this->request = fn (?string $key = null) => $this->postJson(($this->url)('/edit-requests'), ['note' => 'Add one more please'], [...$this->auth, 'Idempotency-Key' => $key ?? (string) Str::uuid()]);
    $this->decide = fn (string $decision) => app(OrderEditRequestService::class)->decide($this->order->fresh(), OrderEditRequest::query()->latest('id')->firstOrFail(), $decision, 'ok', $this->staff);
});
afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('only lets the customer ask for an edit after payment proof', function (): void {
    $this->getJson(($this->url)(), $this->auth)->assertOk()->assertJsonPath('data.can_request_edit', false)->assertJsonPath('data.can_edit', false)->assertJsonPath('data.edit_request', null);
    ($this->request)()->assertStatus(422);

    $this->order->update(['payment_state' => 'submitted']);
    $this->getJson(($this->url)(), $this->auth)->assertOk()->assertJsonPath('data.can_request_edit', true);
    $key = (string) Str::uuid();
    ($this->request)($key)->assertCreated()->assertJsonPath('data.edit_request.status', 'pending')->assertJsonPath('data.edit_request.customer_note', 'Add one more please')->assertJsonPath('data.can_request_edit', false);
    ($this->request)($key)->assertCreated();
    ($this->request)()->assertStatus(422);
    expect(OrderEditRequest::query()->count())->toBe(1);
    expect($this->order->events()->where('key', 'like', 'edit_requested_%')->exists())->toBeTrue();
});

it('blocks editing until the owner approves, then allows exactly one edit', function (): void {
    $this->order->update(['payment_state' => 'submitted']);
    ($this->request)()->assertCreated();
    $this->putJson(($this->url)('/edit'), ($this->retail)(3), $this->auth)->assertStatus(422);
    $this->postJson(($this->url)('/edit/preview'), ($this->retail)(3), $this->auth)->assertStatus(422);

    ($this->decide)('approve');
    $this->getJson(($this->url)(), $this->auth)->assertOk()
        ->assertJsonPath('data.can_edit', true)
        ->assertJsonPath('data.edit_request.status', 'approved')
        ->assertJsonPath('data.edit_lines.0.variant_id', $this->variant->id)
        ->assertJsonPath('data.edit_lines.0.quantity', 2);
    $this->postJson(($this->url)('/edit/preview'), ($this->retail)(3), $this->auth)->assertOk()
        ->assertJsonPath('data.previous_total', '45.00')->assertJsonPath('data.total', '65.00')->assertJsonPath('data.difference', '20.00');

    $this->putJson(($this->url)('/edit'), ($this->retail)(3), $this->auth)->assertOk()
        ->assertJsonPath('data.submission_reference', $this->ref)
        ->assertJsonPath('data.total', '65.00')
        ->assertJsonPath('data.edit_request.status', 'used')
        ->assertJsonPath('data.edit_request.difference', '20.00')
        ->assertJsonPath('data.can_edit', false)
        ->assertJsonPath('data.can_request_edit', true);
    expect($this->order->fresh()->items->sum('quantity'))->toBe(3);

    $this->putJson(($this->url)('/edit'), ($this->retail)(4), $this->auth)->assertStatus(422);
    expect($this->order->fresh()->items->sum('quantity'))->toBe(3);
});

it('keeps a paid order paid, moves its stock and records the amount to refund', function (): void {
    app(OrderWorkflowService::class)->transition($this->order->fresh(), 'paid');
    expect($this->variant->fresh()->stock_quantity)->toBe(3);
    ($this->request)()->assertCreated();
    ($this->decide)('approve');

    $this->putJson(($this->url)('/edit'), ($this->retail)(1), $this->auth)->assertOk()
        ->assertJsonPath('data.payment_state', 'paid')
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.edit_request.previous_total', '45.00')
        ->assertJsonPath('data.edit_request.new_total', '25.00')
        ->assertJsonPath('data.edit_request.difference', '-20.00');
    expect($this->variant->fresh()->stock_quantity)->toBe(4);
    expect($this->order->events()->where('key', 'like', 'edit_used_%')->value('label'))->toContain('refund USD 20.00');
});

it('lets the owner decline or withdraw, and stops requests once shipped', function (): void {
    $this->order->update(['payment_state' => 'submitted']);
    ($this->request)()->assertCreated();
    ($this->decide)('reject');
    $this->getJson(($this->url)(), $this->auth)->assertOk()->assertJsonPath('data.edit_request.status', 'rejected')->assertJsonPath('data.edit_request.owner_note', 'ok')->assertJsonPath('data.can_request_edit', true);
    expect(fn () => ($this->decide)('approve'))->toThrow(HttpException::class);

    ($this->request)()->assertCreated();
    ($this->decide)('approve');
    ($this->decide)('revoke');
    $this->putJson(($this->url)('/edit'), ($this->retail)(3), $this->auth)->assertStatus(422);

    $this->order->update(['status' => 'shipped', 'shipped_at' => now()]);
    ($this->request)()->assertStatus(422);
});

it('hides other customers orders', function (): void {
    $this->order->update(['payment_state' => 'submitted']);
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'Other', 'phone' => '+8801700000003', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $other = app(CustomerService::class)->enroll($contact, true);
    $headers = [...$this->headers, 'Authorization' => 'Bearer '.$other->createToken('other', ['storefront'], now()->addDay())->plainTextToken];

    $this->postJson(($this->url)('/edit-requests'), [], $headers)->assertNotFound();
    $this->putJson(($this->url)('/edit'), ($this->retail)(3), $headers)->assertNotFound();
});
