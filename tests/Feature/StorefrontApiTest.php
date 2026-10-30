<?php

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Database\Seeders\StorefrontBlogSeeder;
use App\Modules\Commerce\Jobs\SendMetaPurchaseEvent;
use App\Modules\Commerce\Jobs\SendTikTokPurchaseEvent;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductOption;
use App\Modules\Commerce\Models\ProductType;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\WhatsAppAuthChallenge;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\ProductService;
use App\Modules\Commerce\Services\ProductTypeService;
use App\Modules\Commerce\Services\WhatsAppCustomerAuthService;
use App\Modules\Commerce\Storefront\Jobs\VerifyStoreDomainJob;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Models\LegacyMapping;
use App\Modules\Commerce\Storefront\Models\ReferralSetting;
use App\Modules\Commerce\Storefront\Models\ReferralTransaction;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Services\CmsService;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\LegacyImportService;
use App\Modules\Commerce\Storefront\Services\ReferralService;
use App\Modules\Commerce\Storefront\Services\RewardWalletService;
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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

it('provides whatsapp_number in product resource from storefront setting contact_phone', function (): void {
    StorefrontSetting::query()->updateOrCreate(
        ['workspace_id' => $this->store->id],
        ['config' => ['contact_phone' => '+18801711657272']]
    );

    $res = $this->getJson('/api/storefront/v1/catalog/products', $this->headers)->assertOk();
    expect($res->json('data.0.whatsapp_number'))->toBe('+18801711657272');
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

it('allows tracking an order using only the tracking reference or number without phone', function (): void {
    [$headers, $data] = storefrontCheckoutFixture($this);
    $orderData = $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data');

    // 1. Track using tracking_code without phone
    $this->getJson('/api/storefront/v1/orders/track?ref='.$orderData['tracking_code'], $headers)
        ->assertOk()
        ->assertJsonPath('data.number', $orderData['number'])
        ->assertJsonPath('data.tracking_code', $orderData['tracking_code']);

    // 2. Track using order number without phone
    $this->getJson('/api/storefront/v1/orders/track?ref='.$orderData['number'], $headers)
        ->assertOk()
        ->assertJsonPath('data.number', $orderData['number']);

    // 3. Reject empty tracking ref
    $this->getJson('/api/storefront/v1/orders/track?ref=', $headers)
        ->assertUnprocessable();

    // 4. Return 404 for unknown tracking code
    $this->getJson('/api/storefront/v1/orders/track?ref=UNKNOWN-12345', $headers)
        ->assertNotFound();
});

it('rewards the referrer after delivery, gives the friend a welcome discount and lets rewards pay for orders', function (): void {
    ReferralSetting::query()->create(['workspace_id' => $this->store->id, 'enabled' => true, 'reward_type' => 'fixed', 'reward_value' => 10, 'referee_discount_type' => 'fixed', 'referee_discount_value' => 5, 'max_redeem_percent' => 100]);
    $referrer = $this->customer;
    $referrerAuth = $this->auth;
    $code = app(ReferralService::class)->codeFor($referrer)->code;
    expect(app(ReferralService::class)->attach($referrer, $code))->toBeNull();

    $friendContact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'Friend', 'phone' => '+8801700000009', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $friend = app(CustomerService::class)->enroll($friendContact, true);
    expect(app(ReferralService::class)->attach($friend, strtolower($code)))->not->toBeNull();
    expect(app(ReferralService::class)->attach($friend, $code))->toBeNull();

    $this->customer = $friend;
    $this->auth = [...$this->headers, 'Authorization' => 'Bearer '.$friend->createToken('test', ['storefront'], now()->addDay())->plainTextToken];
    [$headers, $data] = storefrontCheckoutFixture($this);
    $this->postJson('/api/storefront/v1/checkout/preview', $data, $headers)->assertOk()->assertJsonPath('data.total', '40.00');
    $order = Order::query()->findOrFail($this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data.id'));
    expect(ReferralTransaction::query()->where('order_id', $order->id)->value('status'))->toBe('pending');

    $order->update(['status' => 'shipped']);
    app(OrderWorkflowService::class)->transition($order, 'completed');
    expect(ReferralTransaction::query()->where('order_id', $order->id)->value('status'))->toBe('credited');
    expect(app(RewardWalletService::class)->balance($this->store->id, $referrer->id))->toBe(1000);
    $this->getJson('/api/storefront/v1/me/referral', $referrerAuth)->assertOk()->assertJsonPath('data.stats.credited', 1)->assertJsonPath('data.code', $code);

    $address = CustomerAddress::query()->create(['workspace_id' => $this->store->id, 'customer_id' => $referrer->id, 'label' => 'Home', 'address' => ['name' => 'Customer', 'phone' => '+8801700000000', 'line1' => '1 Road', 'city' => 'Dhaka', 'country' => 'BD']]);
    $token = $this->postJson('/api/storefront/v1/cart', [], $referrerAuth)->assertCreated()->json('data.cart_token');
    $h = [...$referrerAuth, 'X-Cart-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()];
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 2], $h)->assertOk();
    $own = [...$data, 'address_id' => $address->id, 'use_rewards' => true];
    $this->postJson('/api/storefront/v1/checkout/preview', $own, $h)->assertOk()->assertJsonPath('data.total', '35.00')->assertJsonPath('data.rewards.applied', '10.00');
    $paid = Order::query()->findOrFail($this->postJson('/api/storefront/v1/checkout/orders', $own, $h)->assertCreated()->json('data.id'));
    expect(app(RewardWalletService::class)->balance($this->store->id, $referrer->id))->toBe(0);
    app(OrderWorkflowService::class)->transition($paid, 'cancelled');
    expect(app(RewardWalletService::class)->balance($this->store->id, $referrer->id))->toBe(1000);
});

it('caps reward use at the owner-set share of the order total', function (): void {
    ReferralSetting::query()->create(['workspace_id' => $this->store->id, 'enabled' => true, 'max_redeem_percent' => 50, 'min_spend_balance' => 1]);
    $this->getJson('/api/storefront/v1/referral/program', $this->headers)->assertOk()
        ->assertJsonPath('data.max_redeem_percent', 50);

    [$headers, $data] = storefrontCheckoutFixture($this);
    app(RewardWalletService::class)->post($this->store->id, $this->customer->id, 10000, 'adjustment');
    $this->postJson('/api/storefront/v1/checkout/preview', [...$data, 'use_rewards' => true], $headers)->assertOk()
        ->assertJsonPath('data.rewards.max_percent', 50)
        // 50% of the 40.00 product total (shipping is not payable by rewards)
        ->assertJsonPath('data.rewards.available', '20.00')
        ->assertJsonPath('data.rewards.applied', '20.00')
        ->assertJsonPath('data.total', '25.00');

    app(RewardWalletService::class)->post($this->store->id, $this->customer->id, -9000, 'adjustment');
    $this->postJson('/api/storefront/v1/checkout/preview', [...$data, 'use_rewards' => true], $headers)->assertOk()
        ->assertJsonPath('data.rewards.available', '10.00')
        ->assertJsonPath('data.total', '35.00');
});

it('does not let customers withdraw their reward balance', function (): void {
    ReferralSetting::query()->create(['workspace_id' => $this->store->id, 'enabled' => true]);
    app(RewardWalletService::class)->post($this->store->id, $this->customer->id, 800, 'adjustment');
    expect($this->postJson('/api/storefront/v1/me/cashin-requests', ['amount' => 5, 'method' => 'bKash', 'account_details' => '017'], $this->auth)->status())->toBeIn([404, 405]);
    $this->getJson('/api/storefront/v1/me/rewards', $this->auth)->assertOk()->assertJsonPath('data.balance', '8.00')->assertJsonMissingPath('data.cashin');
    $this->getJson('/api/storefront/v1/referral/program', $this->headers)->assertOk()->assertJsonMissingPath('data.cashin_enabled');
    expect(app(RewardWalletService::class)->balance($this->store->id, $this->customer->id))->toBe(800);
    expect(fn () => app(RewardWalletService::class)->post($this->store->id, $this->customer->id, -900, 'redeem'))->toThrow(HttpException::class);
});

it('seeds full demo journal articles with covers for a single store', function (): void {
    app(StorefrontBlogSeeder::class)->seedWorkspace($this->store->id);
    app(StorefrontBlogSeeder::class)->seedWorkspace($this->store->id);

    $list = $this->getJson('/api/storefront/v1/blogs', $this->headers)->assertOk()->assertJsonCount(6, 'data.data');
    expect($list->json('data.data.0.slug'))->toBe('guide-to-heavyweight-streetwear')
        ->and(collect($list->json('data.data'))->every(fn (array $post): bool => str_starts_with($post['data']['cover_image'], '/storage/') && filled($post['data']['category'])))->toBeTrue();

    $post = $this->getJson('/api/storefront/v1/blogs/denim-guide-raw-rinsed-washed', $this->headers)->assertOk();
    expect(str_word_count(strip_tags($post->json('data.data.content'))))->toBeGreaterThan(350);

    $this->getJson('/api/storefront/v1/blogs', [...$this->headers, 'X-Store-Host' => 'two.localhost'])->assertOk()->assertJsonCount(0, 'data.data');
});

it('serves category-specific attributes, filters and facets for non-apparel products', function (): void {
    $service = app(ProductTypeService::class);
    $service->ensureDefaults($this->store->id);
    $jewelryType = ProductType::query()->where('workspace_id', $this->store->id)->where('slug', 'jewelry')->firstOrFail();
    $parent = Category::query()->create(['workspace_id' => $this->store->id, 'name' => 'Jewelry', 'slug' => 'jewelry', 'product_type_id' => $jewelryType->id, 'is_active' => true]);
    $child = Category::query()->create(['workspace_id' => $this->store->id, 'parent_id' => $parent->id, 'name' => 'Rings', 'slug' => 'rings', 'is_active' => true]);

    $values = $service->sanitizeValues($jewelryType, ['metal' => 'Gold', 'weight_g' => '4.5', 'bogus' => 'x']);
    expect($values)->toBe(['metal' => 'Gold', 'weight_g' => 4.5]);

    $ring = Product::query()->create(['workspace_id' => $this->store->id, 'category_id' => $child->id, 'name' => 'Gold Ring', 'slug' => 'gold-ring', 'sku' => 'gold-ring', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 500, 'attribute_values' => $values]);
    Product::query()->create(['workspace_id' => $this->store->id, 'category_id' => $child->id, 'name' => 'Silver Ring', 'slug' => 'silver-ring', 'sku' => 'silver-ring', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 90, 'attribute_values' => ['metal' => 'Silver']]);

    $this->getJson('/api/storefront/v1/catalog/products/gold-ring', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.product_type.layout', 'jewelry')
        ->assertJsonPath('data.attributes.0.key', 'metal')
        ->assertJsonPath('data.attributes.0.value', 'Gold');

    $this->getJson('/api/storefront/v1/catalog/products?category=jewelry&attr[metal]=Gold', $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'gold-ring');

    $facets = $this->getJson('/api/storefront/v1/catalog/products/filters?category=rings', $this->headers)->assertOk()->json('data');
    expect($facets['product_type']['layout'])->toBe('jewelry');
    $metal = collect($facets['attributes'])->firstWhere('key', 'metal');
    expect(collect($metal['values'])->pluck('count', 'value')->all())->toBe(['Gold' => 1, 'Silver' => 1]);

    $categories = $this->getJson('/api/storefront/v1/catalog/categories', $this->headers)->assertOk()->json('data');
    expect(collect($categories)->firstWhere('slug', 'rings')['product_type']['slug'])->toBe('jewelry');
    expect($ring->fresh()->attribute_values['metal'])->toBe('Gold');
});

it('rejects invalid attribute values and treats untyped categories as apparel', function (): void {
    $service = app(ProductTypeService::class);
    $service->ensureDefaults($this->store->id);
    $food = ProductType::query()->where('workspace_id', $this->store->id)->where('slug', 'food')->firstOrFail();

    expect(fn () => $service->sanitizeValues($food, ['diet' => 'Pizza']))->toThrow(ValidationException::class);

    $plain = Category::query()->create(['workspace_id' => $this->store->id, 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
    expect($service->forCategory($plain, $this->store->id)->slug)->toBe('apparel');
});

it('filters products by option value and exposes option facets', function (): void {
    $shade = fn (Product $product, string $value) => ProductOption::query()->create(['workspace_id' => $this->store->id, 'product_id' => $product->id, 'name' => 'Shade', 'code' => 'shade', 'position' => 0])
        ->values()->create(['workspace_id' => $this->store->id, 'value' => $value, 'position' => 0]);
    $make = fn (string $slug) => Product::query()->create(['workspace_id' => $this->store->id, 'name' => $slug, 'slug' => $slug, 'sku' => $slug, 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 10]);
    $shade($make('lip-red'), 'Red');
    $shade($make('lip-nude'), 'Nude');

    $this->getJson('/api/storefront/v1/catalog/products?option[shade]=Red', $this->headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'lip-red');
    $options = $this->getJson('/api/storefront/v1/catalog/products/filters', $this->headers)->assertOk()->json('data.options');
    expect(collect($options)->firstWhere('code', 'shade')['values'])->toHaveCount(2);
});

it('keeps non-apparel products retail-only', function (): void {
    $service = app(ProductService::class);
    app(ProductTypeService::class)->ensureDefaults($this->store->id);
    $food = ProductType::query()->where('workspace_id', $this->store->id)->where('slug', 'food')->firstOrFail();
    $category = Category::query()->create(['workspace_id' => $this->store->id, 'name' => 'Snacks', 'slug' => 'snacks', 'product_type_id' => $food->id, 'is_active' => true]);

    $product = $service->createDraft($this->store->id, ['name' => 'Chips', 'category_id' => $category->id, 'selling_mode' => 'both', 'ws_enabled' => true]);

    expect($product->selling_mode)->toBe('retail')->and($product->ws_enabled)->toBeFalse();
});

it('queues one Meta purchase event per new order with validated browser details', function (): void {
    Queue::fake();
    [$headers, $data] = storefrontCheckoutFixture($this);
    $headers = [...$headers, 'X-Client-Ip' => '203.0.113.9', 'X-Client-User-Agent' => 'Mozilla/5.0', 'X-Meta-Fbp' => 'fb.1.1710000000.123', 'X-Meta-Fbc' => 'not valid'];

    $order = $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data');
    $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertOk();

    Queue::assertPushed(SendMetaPurchaseEvent::class, 1);
    Queue::assertPushed(SendTikTokPurchaseEvent::class, 1);
    Queue::assertPushed(SendMetaPurchaseEvent::class, fn (SendMetaPurchaseEvent $job): bool => $job->orderId === $order['id']
        && $job->client['ip'] === '203.0.113.9'
        && $job->client['user_agent'] === 'Mozilla/5.0'
        && $job->client['fbp'] === 'fb.1.1710000000.123'
        && $job->client['fbc'] === null);
});

function fakeDnsJob(int $domainId, array $records): VerifyStoreDomainJob
{
    return new class($domainId, $records) extends VerifyStoreDomainJob
    {
        public function __construct(int $domainId, private array $records)
        {
            parent::__construct($domainId);
        }

        protected function lookup(string $host, int $type): array
        {
            return $this->records[$host.'|'.$type] ?? [];
        }
    };
}

it('activates www when the apex is already active and the host routes to the target', function (): void {
    config(['storefront.dns_target' => 'edge.example.net']);
    StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'shop.test', 'status' => 'active', 'verification_token' => 'a']);
    $www = StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'www.shop.test', 'verification_token' => 'b']);

    fakeDnsJob($www->id, ['www.shop.test|'.DNS_CNAME => [['target' => 'edge.example.net.']]])->handle();

    expect($www->fresh()->status)->toBe('active')->and($www->fresh()->verification_error)->toBeNull();
});

it('keeps www pending with a reason when no sibling is active and the TXT record is missing', function (): void {
    config(['storefront.dns_target' => 'edge.example.net']);
    $www = StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'www.shop.test', 'verification_token' => 'b']);

    fakeDnsJob($www->id, ['www.shop.test|'.DNS_CNAME => [['target' => 'edge.example.net']]])->handle();

    expect($www->fresh()->status)->toBe('verifying')->and($www->fresh()->verification_error)->toBe('txt_missing');
});

it('accepts matching A records when no CNAME is visible', function (): void {
    config(['storefront.dns_target' => 'edge.example.net']);
    $d = StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'shop.test', 'verification_token' => 'tok']);

    fakeDnsJob($d->id, [
        '_verify.shop.test|'.DNS_TXT => [['txt' => 'tok']],
        'shop.test|'.DNS_A => [['ip' => '1.2.3.4']],
        'edge.example.net|'.DNS_A => [['ip' => '1.2.3.4']],
    ])->handle();

    expect($d->fresh()->status)->toBe('active');
});

it('serves the www sibling of an active apex and redirects via the primary host', function (): void {
    StoreDomain::query()->where('workspace_id', $this->store->id)->update(['is_primary' => false]);
    StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'shop.test', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'a']);

    $this->getJson('/internal/stores/resolve?host=www.shop.test', $this->headers)->assertOk()
        ->assertJsonPath('data.store_id', $this->store->id)->assertJsonPath('data.primary_host', 'shop.test')->assertJsonPath('data.host', 'www.shop.test');
    $this->getJson('/internal/stores/resolve?host=www.unrelated.test', $this->headers)->assertNotFound();

    StoreDomain::query()->where('host', 'shop.test')->update(['status' => 'failed']);
    $this->getJson('/internal/stores/resolve?host=www.shop.test', $this->headers)->assertNotFound();
});

it('activates a www host that shares the active apex servers even when it points at a CDN', function (): void {
    config(['storefront.dns_target' => 'edge.example.net']);
    StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'shop.test', 'status' => 'active', 'verification_token' => 'a']);
    $www = StoreDomain::query()->create(['workspace_id' => $this->store->id, 'host' => 'www.shop.test', 'verification_token' => 'b']);

    fakeDnsJob($www->id, [
        'www.shop.test|'.DNS_CNAME => [['target' => 'www.shop.test.cdn.example.net.']],
        'www.shop.test|'.DNS_A => [['ip' => '9.9.9.9']],
        'shop.test|'.DNS_A => [['ip' => '9.9.9.9']],
    ])->handle();

    expect($www->fresh()->status)->toBe('active');
});
