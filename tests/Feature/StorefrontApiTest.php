<?php

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\WhatsAppAuthChallenge;
use App\Modules\Commerce\Services\WhatsAppCustomerAuthService;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Models\LegacyMapping;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Services\CmsService;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\LegacyImportService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingRate;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    $this->variant = ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $this->product->id, 'sku' => 'shirt-m', 'meta_retailer_id' => 'shirt-m', 'size' => 'M', 'price' => 20, 'stock_quantity' => 5, 'status' => 'active']);
});
afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('rejects missing secrets and never falls back for unknown hosts', function (): void {
    $this->getJson('/api/storefront/v1/catalog/products')->assertUnauthorized();
    $this->getJson('/api/storefront/v1/catalog/products', [...$this->headers, 'X-Store-Host' => 'unknown.localhost'])->assertNotFound();
    $this->getJson('/internal/stores/resolve?host=one.localhost', $this->headers)->assertOk()->assertJsonPath('data.store_id', $this->store->id);
});
it('isolates catalog and rejects unbounded pagination', function (): void {
    $this->getJson('/api/storefront/v1/catalog/products', $this->headers)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/storefront/v1/catalog/products', [...$this->headers, 'X-Store-Host' => 'two.localhost'])->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/storefront/v1/catalog/products/shirt', [...$this->headers, 'X-Store-Host' => 'two.localhost'])->assertNotFound();
    $this->getJson('/api/storefront/v1/catalog/products?per_page=100000', $this->headers)->assertUnprocessable();
});

it('uses published workspace collections and real sale prices', function (): void {
    StorefrontContent::query()->create(['workspace_id' => $this->store->id, 'type' => 'collection', 'slug' => 'featured', 'data' => ['title' => 'Featured', 'product_ids' => [$this->product->id]], 'published_at' => now()]);
    $this->getJson('/api/storefront/v1/catalog/collections/featured', $this->headers)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/storefront/v1/catalog/collections/featured', [...$this->headers, 'X-Store-Host' => 'two.localhost'])->assertNotFound();
    $this->getJson('/api/storefront/v1/catalog/collections/deals', $this->headers)->assertOk()->assertJsonCount(0, 'data');
    $this->variant->update(['compare_at_price' => 30]);
    $this->getJson('/api/storefront/v1/catalog/collections/deals', $this->headers)->assertOk()->assertJsonCount(1, 'data');
});
it('does not turn owner contacts into customers and rejects cross-store tokens', function (): void {
    Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'CRM only', 'phone' => '+8801700000001', 'source' => 'website', 'opt_in_status' => 'unknown']));
    expect(Customer::query()->count())->toBe(1);
    $this->getJson('/api/storefront/v1/me', $this->auth)->assertOk()->assertJsonPath('data.name', 'Customer');
    $this->getJson('/api/storefront/v1/me', [...$this->auth, 'X-Store-Host' => 'two.localhost'])->assertUnauthorized();
});
it('requires fresh verification for password setup', function (): void {
    $this->putJson('/api/storefront/v1/auth/password', ['password' => 'a-long-password', 'password_confirmation' => 'a-long-password'], $this->auth)->assertForbidden();
});
it('keeps addresses private and locks the login phone', function (): void {
    $address = CustomerAddress::query()->create(['workspace_id' => $this->other->id, 'customer_id' => 999, 'label' => 'Private', 'address' => ['line1' => 'Other customer']]);
    $this->deleteJson('/api/storefront/v1/me/addresses/'.$address->id, [], $this->auth)->assertNotFound();
    $this->putJson('/api/storefront/v1/me', ['name' => 'New', 'phone' => '+8801700000002'], $this->auth)->assertUnprocessable();
    $this->putJson('/api/storefront/v1/me', ['name' => 'Shared name', 'email' => 'customer@example.test'], $this->auth)->assertOk()->assertJsonPath('data.name', 'Shared name');
    expect($this->contact->fresh()->email)->toBe('customer@example.test');
    expect($this->customer->fresh()->verified_phone)->toBe('+8801700000000');
});
it('recalculates cart prices and isolates guest tokens', function (): void {
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->assertCreated()->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token];
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 2, 'price' => 1], $h)->assertOk()->assertJsonPath('data.subtotal', '40.00');
    $this->product->update(['single_piece_price' => 30]);
    $this->getJson('/api/storefront/v1/cart', $h)->assertOk()->assertJsonPath('data.subtotal', '60.00');
    $this->getJson('/api/storefront/v1/cart', [...$h, 'X-Store-Host' => 'two.localhost'])->assertNotFound();
});
it('clears all items and coupon from the active cart', function (): void {
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->assertCreated()->json('data.cart_token');
    $h = [...$this->headers, 'X-Cart-Token' => $token];
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 2], $h)->assertOk();
    $this->deleteJson('/api/storefront/v1/cart/clear', [], $h)->assertOk()->assertJsonPath('data.items', [])->assertJsonPath('data.total', '0.00');
});
it('merges a guest cart once and rotates its token', function (): void {
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->json('data.cart_token');
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 2], [...$this->headers, 'X-Cart-Token' => $token])->assertOk();
    $merged = $this->postJson('/api/storefront/v1/cart/merge', [], [...$this->auth, 'X-Cart-Token' => $token])->assertOk()->json('data.cart_token');
    expect($merged)->not->toBe($token);
    $this->getJson('/api/storefront/v1/cart', [...$this->headers, 'X-Cart-Token' => $merged])->assertNotFound();
    $this->postJson('/api/storefront/v1/cart/merge', [], [...$this->auth, 'X-Cart-Token' => $token])->assertNotFound();
});
it('keeps draft changes out of published pages and scopes preview tokens', function (): void {
    $page = StorefrontPage::query()->where('workspace_id', $this->store->id)->where('slug', 'home')->firstOrFail();
    $page->update(['title' => 'Draft title', 'status' => 'published', 'published_at' => now(), 'published_content' => ['title' => 'Published title', 'sections' => []]]);
    $this->getJson('/api/storefront/v1/cms/pages/home', $this->headers)->assertOk()->assertJsonPath('data.title', 'Published title');
    $token = app(CmsService::class)->previewToken($this->store->id, 'home');
    $this->getJson('/api/storefront/v1/cms/pages/home?preview_token='.$token, $this->headers)->assertOk()->assertJsonPath('data.title', 'Draft title');
    expect(app(CmsService::class)->verifyPreview($this->other->id, 'home', $token))->toBeFalse();
});

function storefrontCheckoutFixture($test): array
{
    $test->store->update(['settings' => ['commerce' => ['currency' => 'USD', 'payment_methods' => [['id' => 'bank', 'name' => 'Bank transfer', 'active' => '1', 'recipient_details' => 'Test bank account', 'instructions' => 'Transfer and upload proof', 'sort_order' => 0, 'fields' => []]]]]]);
    $zone = ShippingZone::query()->create(['workspace_id' => $test->store->id, 'name' => 'Bangladesh', 'code' => 'BD', 'is_active' => true]);
    $zone->countries()->create(['workspace_id' => $test->store->id, 'country_code' => 'BD']);
    $method = ShippingMethod::query()->create(['workspace_id' => $test->store->id, 'name' => 'Delivery', 'code' => 'standard', 'type' => 'standard', 'is_active' => true]);
    ShippingRate::query()->create(['workspace_id' => $test->store->id, 'shipping_zone_id' => $zone->id, 'shipping_method_id' => $method->id, 'min_weight_kg' => 0, 'max_weight_kg' => 100, 'price' => 5, 'price_per_kg' => 0, 'currency' => 'USD', 'is_active' => true]);
    $address = CustomerAddress::query()->create(['workspace_id' => $test->store->id, 'customer_id' => $test->customer->id, 'label' => 'Home', 'address' => ['name' => 'Customer', 'phone' => '+8801700000000', 'line1' => '123 Test road', 'city' => 'Dhaka', 'country' => 'BD']]);
    $token = $test->postJson('/api/storefront/v1/cart', [], $test->auth)->assertCreated()->json('data.cart_token');
    $headers = [...$test->auth, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    $test->postJson('/api/storefront/v1/cart/items', ['variant_id' => $test->variant->id, 'quantity' => 2], $headers)->assertOk();

    return [$headers, ['address_id' => $address->id, 'shipping_method_id' => $method->id, 'payment_method_id' => 'bank']];
}

it('creates one order on retries and rejects changed idempotency payloads', function (): void {
    [$headers,$data] = storefrontCheckoutFixture($this);
    $this->getJson('/api/storefront/v1/checkout/shipping-methods?address_id='.$data['address_id'], $headers)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/storefront/v1/checkout/payment-methods', $headers)->assertOk()->assertJsonCount(1, 'data');
    $this->postJson('/api/storefront/v1/checkout/preview', $data, $headers)->assertOk()->assertJsonPath('data.total', '45.00');
    $order = $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data');
    $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertOk()->assertJsonPath('data.id', $order['id']);
    $this->postJson('/api/storefront/v1/checkout/orders', [...$data, 'payment_method_id' => 'other'], $headers)->assertConflict();
    expect(Order::query()->count())->toBe(1);
    expect($this->variant->fresh()->stock_quantity)->toBe(5);
    expect(OrderReservation::query()->sum('quantity'))->toBe(2);
    $this->getJson('/api/storefront/v1/me/orders/'.$order['submission_reference'], $this->auth)->assertOk();
    $otherContact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'Other', 'phone' => '+8801700000003', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $other = app(CustomerService::class)->enroll($otherContact, true);
    $h = [...$this->headers, 'Authorization' => 'Bearer '.$other->createToken('other', ['storefront'], now()->addDay())->plainTextToken];
    $this->getJson('/api/storefront/v1/me/orders/'.$order['submission_reference'], $h)->assertNotFound();
    $this->get('/api/storefront/v1/me/orders/'.$order['submission_reference'].'/invoice', $this->auth)->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('rejects stock changes and exhausted coupons at checkout', function (): void {
    [$headers,$data] = storefrontCheckoutFixture($this);
    Coupon::query()->create(['workspace_id' => $this->store->id, 'code' => 'SAVE10', 'type' => 'percentage', 'value' => 10, 'min_order' => 0, 'active' => true]);
    $this->postJson('/api/storefront/v1/cart/coupon', ['code' => 'SAVE10'], $headers)->assertOk()->assertJsonPath('data.discount', '4.00');
    $this->variant->update(['stock_quantity' => 1]);
    $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertUnprocessable();
    expect(Order::query()->count())->toBe(0);
    $this->variant->update(['stock_quantity' => 5]);
    Coupon::query()->where('code', 'SAVE10')->update(['usage_limit' => 0]);
    $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertUnprocessable();
});

it('submits payment evidence without marking the order paid', function (): void {
    Storage::fake('local');
    [$headers,$data] = storefrontCheckoutFixture($this);
    $order = $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data');
    $this->post('/api/storefront/v1/orders/'.$order['submission_reference'].'/evidence', ['payment_method_id' => 'bank', 'transaction_id' => 'BANK-123', 'receipts' => [UploadedFile::fake()->image('receipt.png')]], $this->auth)->assertOk();
    expect(Order::query()->findOrFail($order['id'])->payment_state)->toBe('submitted');
    expect($this->variant->fresh()->stock_quantity)->toBe(5);
});

it('blocks expired tokens and tokens after the CRM phone changes', function (): void {
    $expired = $this->customer->createToken('expired', ['storefront'], now()->subMinute())->plainTextToken;
    $this->getJson('/api/storefront/v1/me', [...$this->headers, 'Authorization' => 'Bearer '.$expired])->assertUnauthorized();
    $this->contact->update(['phone' => '+8801700000099']);
    $this->getJson('/api/storefront/v1/me', $this->auth)->assertUnauthorized();
});

it('inventories and resumes legacy batches without duplicating records', function (): void {
    config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
    $legacy = DB::connection('legacy_test');
    $legacy->getSchemaBuilder()->create('settings', function ($table): void {
        $table->id();
        $table->string('key');
        $table->text('value')->nullable();
    });
    $legacy->table('settings')->insert(['id' => 1, 'key' => 'site_name', 'value' => 'Imported store']);
    $import = app(LegacyImportService::class);
    $dry = $import->run('legacy_test', $this->store->id, true);
    expect($dry['tables']['settings']['source'])->toBe(1);
    expect(LegacyMapping::query()->count())->toBe(0);
    $first = $import->run('legacy_test', $this->store->id, false);
    $second = $import->run('legacy_test', $this->store->id, false);
    expect($first['tables']['settings']['imported'])->toBe(1);
    expect($second['tables']['settings']['already_mapped'])->toBe(1);
    expect($second['ready_for_cutover'])->toBeFalse();
    expect(StorefrontSetting::query()->where('workspace_id', $this->store->id)->firstOrFail()->config['name'])->toBe('Imported store');
    DB::disconnect('legacy_test');
});

it('consumes OTP grants once and rejects expired grants', function (): void {
    $binding = str_repeat('b', 32);
    $grant = str_repeat('g', 64);
    $auth = Mockery::mock(WhatsAppCustomerAuthService::class);
    $auth->shouldReceive('verify')->andReturn(['phone' => $this->contact->phone, 'grant' => $grant]);
    app()->instance(WhatsAppCustomerAuthService::class, $auth);
    $id = (string) Str::uuid();
    WhatsAppAuthChallenge::query()->create(['id' => $id, 'workspace_id' => $this->store->id, 'phone' => $this->contact->phone, 'session_hash' => hash('sha256', $binding), 'code_hash' => hash('sha256', '123456'), 'expires_at' => now()->addMinutes(5), 'verified_at' => now(), 'grant_hash' => hash('sha256', $grant), 'grant_expires_at' => now()->addMinutes(10), 'consented_at' => now()]);
    $payload = ['code' => '123456', 'session_binding' => $binding];
    $url = '/api/storefront/v1/auth/whatsapp/challenges/'.$id.'/verify';
    $this->postJson($url, $payload, $this->headers)->assertOk();
    $this->postJson($url, $payload, $this->headers)->assertUnprocessable();
    WhatsAppAuthChallenge::query()->whereKey($id)->update(['consumed_at' => null, 'grant_expires_at' => now()->subSecond()]);
    $this->postJson($url, $payload, $this->headers)->assertUnprocessable();
});

it('allows password setup only after fresh OTP verification', function (): void {
    $settings = StorefrontSetting::query()->where('workspace_id', $this->store->id)->firstOrFail();
    $settings->update(['config' => [...$settings->config, 'password_login' => true]]);
    $password = ['password' => 'a-secure-password-123', 'password_confirmation' => 'a-secure-password-123'];
    $this->putJson('/api/storefront/v1/auth/password', $password, $this->auth)->assertForbidden();
    $fresh = $this->customer->createToken('verified', ['storefront', 'password:verified'], now()->addMinutes(10))->plainTextToken;
    $this->putJson('/api/storefront/v1/auth/password', $password, [...$this->headers, 'Authorization' => 'Bearer '.$fresh])->assertOk();
    $this->postJson('/api/storefront/v1/auth/password/login', ['phone' => $this->contact->phone, 'password' => $password['password']], $this->headers)->assertOk();
    $this->postJson('/api/storefront/v1/auth/password/login', ['phone' => $this->contact->phone, 'password' => 'incorrect'], $this->headers)->assertUnprocessable();
});

it('serves mobile storefront routes via X-Store-Host without requiring platform secret', function (): void {
    $mobileHeaders = ['X-Store-Host' => 'one.localhost'];
    $this->getJson('/api/v1/mobile/storefront/catalog/products', $mobileHeaders)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/mobile/storefront/catalog/products', ['X-Store-Host' => 'unknown.localhost'])->assertNotFound();
    $token = $this->customer->createToken('mobile', ['storefront'], now()->addDay())->plainTextToken;
    $this->getJson('/api/v1/mobile/storefront/me', [...$mobileHeaders, 'Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.name', 'Customer');
});

it('filters mobile owner orders with real-time KPI counts and auto-packs on packed transition', function (): void {
    Schema::table('users', fn ($t) => $t->softDeletes());
    (require database_path('migrations/2026_02_09_055051_create_permission_tables.php'))->up();
    Gate::before(fn () => true);
    $this->mock(AuditLogService::class)->shouldIgnoreMissing();
    $ownerId = DB::table('users')->insertGetId(['name' => 'Owner']);
    $this->store->update(['owner_id' => $ownerId]);
    $owner = new User;
    $owner->forceFill(['id' => $ownerId, 'first_name' => 'Store', 'last_name' => 'Owner']);
    $owner->exists = true;

    [$headers, $data] = storefrontCheckoutFixture($this);
    $orderData = $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data');
    $order = Order::query()->findOrFail($orderData['id']);

    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/mobile/workspaces/{$this->store->id}/orders?status=new")
        ->assertOk()
        ->assertJsonPath('counts.new', 1)
        ->assertJsonCount(1, 'data');

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/mobile/workspaces/{$this->store->id}/orders/{$order->id}/status", ['status' => 'paid'])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/mobile/workspaces/{$this->store->id}/orders/{$order->id}/status", ['status' => 'processing'])
        ->assertOk()
        ->assertJsonPath('data.status', 'processing');

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/mobile/workspaces/{$this->store->id}/orders/{$order->id}/status", ['status' => 'packed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'packed');

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/mobile/workspaces/{$this->store->id}/orders/{$order->id}/status", [
            'status' => 'shipped',
            'carrier' => 'Pathao',
            'tracking_number' => 'PTH-998877',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.tracking_number', 'PTH-998877');
});
