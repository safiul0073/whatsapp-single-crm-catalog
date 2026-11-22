<?php

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Commerce\Database\Seeders\StorefrontBlogSeeder;
use App\Modules\Commerce\Jobs\SendMetaEvent;
use App\Modules\Commerce\Jobs\SendMetaPurchaseEvent;
use App\Modules\Commerce\Jobs\SendTikTokPurchaseEvent;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\MobileCustomerDevice;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductOption;
use App\Modules\Commerce\Models\ProductType;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\WhatsAppAuthChallenge;
use App\Modules\Commerce\Services\MetaConversionsService;
use App\Modules\Commerce\Services\OrderInventoryService;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\ProductService;
use App\Modules\Commerce\Services\ProductTypeService;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Services\WhatsAppCustomerAuthService;
use App\Modules\Commerce\Storefront\Jobs\VerifyStoreDomainJob;
use App\Modules\Commerce\Storefront\Models\CashinRequest;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Models\LegacyMapping;
use App\Modules\Commerce\Storefront\Models\ProductReview;
use App\Modules\Commerce\Storefront\Models\ReferralSetting;
use App\Modules\Commerce\Storefront\Models\ReferralTransaction;
use App\Modules\Commerce\Storefront\Models\RefundRequest;
use App\Modules\Commerce\Storefront\Models\RewardLedgerEntry;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Services\CmsService;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\LegacyImportService;
use App\Modules\Commerce\Storefront\Services\ReferralService;
use App\Modules\Commerce\Storefront\Services\RefundRequestService;
use App\Modules\Commerce\Storefront\Services\RewardWalletService;
use App\Modules\Commerce\Storefront\Services\StorePushService;
use App\Modules\Commerce\Storefront\Services\TrackingSettingsService;
use App\Modules\Commerce\Storefront\Services\WithdrawalService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\NotificationTemplates\Notifications\MobileAlert;
use App\Modules\Shipping\Models\ShippingMethod;
use App\Modules\Shipping\Models\ShippingRate;
use App\Modules\Shipping\Models\ShippingZone;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use App\Modules\Workspaces\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Contract\Messaging;
use NotificationChannels\Fcm\FcmChannel;
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

it('shows the recent orders scrolling line by default and lets the owner turn it off', function (): void {
    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)->assertOk()->assertJsonPath('data.recent_orders_ticker', true);

    StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->store->id], ['config' => ['recent_orders_ticker' => '0']]);

    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)->assertOk()->assertJsonPath('data.recent_orders_ticker', false);
});

it('shows the how-to-order welcome popup by default and lets the owner turn it off', function (): void {
    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)->assertOk()->assertJsonPath('data.welcome_guide', true);

    StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->store->id], ['config' => ['welcome_guide' => '0']]);

    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)->assertOk()->assertJsonPath('data.welcome_guide', false);
});

it('exposes sold counts and masked recent orders', function (): void {
    $make = function (string $status, int $qty, string $name): void {
        $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => null, 'number' => 'T-'.Str::random(6), 'status' => $status, 'currency' => 'BDT', 'subtotal' => 0, 'total' => 0, 'customer_snapshot' => ['name' => $name, 'phone' => '+8801711111111']]);
        $order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $this->variant->id, 'retailer_id' => 'shirt-m', 'sku' => 'shirt-m', 'product_name' => 'Shirt', 'quantity' => $qty, 'unit_price' => 20, 'line_total' => 20 * $qty]);
    };
    $make('processing', 100, 'john doe');
    $make('cancelled', 7, 'Jane Roe');

    $this->getJson('/api/storefront/v1/catalog/products', $this->headers)->assertOk()->assertJsonPath('data.0.sold_count', 100);
    $this->getJson('/api/storefront/v1/catalog/products/shirt', $this->headers)->assertOk()->assertJsonPath('data.sold_count', 100);
    $res = $this->getJson('/api/storefront/v1/catalog/recent-orders', $this->headers)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'John D.')->assertJsonPath('data.0.first_name', 'John')->assertJsonMissingPath('data.0.full_name')->assertJsonPath('data.0.total_items', 100)->assertJsonPath('data.0.quantity', 100)->assertJsonPath('data.0.product_slug', 'shirt');
    expect($res->getContent())->not->toContain('8801711111111');
    $this->getJson('/api/storefront/v1/catalog/recent-orders', [...$this->headers, 'X-Store-Host' => 'two.localhost'])->assertOk()->assertJsonCount(0, 'data');
});

it('returns the full name and the total items across all order lines for the recent orders ticker', function (): void {
    $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => null, 'number' => 'T-'.Str::random(6), 'status' => 'processing', 'currency' => 'BDT', 'subtotal' => 0, 'total' => 0, 'customer_snapshot' => ['name' => '  test   customer ']]);
    foreach ([3, 2] as $qty) {
        $order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $this->variant->id, 'retailer_id' => 'shirt-m', 'sku' => 'shirt-m', 'product_name' => 'Shirt', 'quantity' => $qty, 'unit_price' => 20, 'line_total' => 20 * $qty]);
    }

    $this->getJson('/api/storefront/v1/catalog/recent-orders', $this->headers)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.first_name', 'Test')->assertJsonPath('data.0.name', 'Test C.')->assertJsonPath('data.0.total_items', 5);
});

it('shows recent orders from the last 30 days only', function (): void {
    foreach (['In Window' => 29, 'Too Old' => 31] as $name => $daysAgo) {
        $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => null, 'number' => 'T-'.Str::random(6), 'status' => 'processing', 'currency' => 'BDT', 'subtotal' => 0, 'total' => 0, 'customer_snapshot' => ['name' => $name]]);
        $order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $this->variant->id, 'retailer_id' => 'shirt-m', 'sku' => 'shirt-m', 'product_name' => 'Shirt', 'quantity' => 1, 'unit_price' => 20, 'line_total' => 20]);
        $order->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    }

    $this->getJson('/api/storefront/v1/catalog/recent-orders', $this->headers)->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'In');
});

it('shows only the five newest recent orders, newest first', function (): void {
    foreach (['One', 'Two', 'Three', 'Four', 'Five', 'Six'] as $name) {
        $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => null, 'number' => 'T-'.Str::random(6), 'status' => 'processing', 'currency' => 'BDT', 'subtotal' => 0, 'total' => 0, 'customer_snapshot' => ['name' => $name]]);
        $order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $this->variant->id, 'retailer_id' => 'shirt-m', 'sku' => 'shirt-m', 'product_name' => 'Shirt', 'quantity' => 1, 'unit_price' => 20, 'line_total' => 20]);
    }

    $res = $this->getJson('/api/storefront/v1/catalog/recent-orders', $this->headers)->assertOk()->assertJsonCount(5, 'data');
    expect(array_column($res->json('data'), 'first_name'))->toBe(['Six', 'Five', 'Four', 'Three', 'Two']);
});

it('returns only a valid two-letter country code for the recent orders ticker', function (): void {
    $make = function (?array $address, string $name): void {
        $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => null, 'number' => 'T-'.Str::random(6), 'status' => 'processing', 'currency' => 'BDT', 'subtotal' => 0, 'total' => 0, 'customer_snapshot' => ['name' => $name], 'shipping_address' => $address]);
        $order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $this->variant->id, 'retailer_id' => 'shirt-m', 'sku' => 'shirt-m', 'product_name' => 'Shirt', 'quantity' => 1, 'unit_price' => 20, 'line_total' => 20]);
    };
    $make(['country' => 'bd', 'line1' => '1 Secret Road', 'city' => 'Dhaka'], 'Has Country');
    $make(['country' => 'Bangladesh'], 'Bad Country');
    $make(null, 'No Country');

    $res = $this->getJson('/api/storefront/v1/catalog/recent-orders', $this->headers)->assertOk()->assertJsonCount(3, 'data');
    expect(collect($res->json('data'))->pluck('country', 'first_name')->all())->toBe(['No' => null, 'Bad' => null, 'Has' => 'BD']);
    expect($res->getContent())->not->toContain('Secret Road')->not->toContain('Dhaka');
});

it('falls back to Someone when the recent order has no customer name', function (): void {
    $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => null, 'number' => 'T-'.Str::random(6), 'status' => 'processing', 'currency' => 'BDT', 'subtotal' => 0, 'total' => 0]);
    $order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $this->variant->id, 'retailer_id' => 'shirt-m', 'sku' => 'shirt-m', 'product_name' => 'Shirt', 'quantity' => 1, 'unit_price' => 20, 'line_total' => 20]);

    $this->getJson('/api/storefront/v1/catalog/recent-orders', $this->headers)->assertOk()->assertJsonPath('data.0.first_name', 'Someone');
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

it('hides small sold counts and ranks top sellers by recent real sales with a fallback', function (): void {
    $product = function (string $slug): ProductVariant {
        $p = Product::query()->create(['workspace_id' => $this->store->id, 'name' => ucfirst($slug), 'slug' => $slug, 'sku' => $slug, 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'retail', 'single_piece_price' => 20]);

        return ProductVariant::query()->create(['workspace_id' => $this->store->id, 'product_id' => $p->id, 'sku' => $slug.'-m', 'meta_retailer_id' => $slug.'-m', 'size' => 'M', 'price' => 20, 'stock_quantity' => 5, 'status' => 'active']);
    };
    $sell = function (ProductVariant $variant, int $qty, string $status = 'processing', int $daysAgo = 0, bool $deleted = false): void {
        $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => null, 'number' => 'T-'.Str::random(6), 'status' => $status, 'currency' => 'BDT', 'subtotal' => 0, 'total' => 0]);
        $order->items()->create(['workspace_id' => $this->store->id, 'variant_id' => $variant->id, 'retailer_id' => $variant->sku, 'sku' => $variant->sku, 'product_name' => $variant->sku, 'quantity' => $qty, 'unit_price' => 20, 'line_total' => 20 * $qty]);
        $order->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        if ($deleted) {
            $order->delete();
        }
    };
    $old = $product('old-hit');
    $recent = $product('recent-hit');
    $refunded = $product('refunded');
    $product('quiet');
    $sell($old, 50, daysAgo: 200);
    $sell($recent, 12);
    $sell($refunded, 100, 'refunded');
    $sell($refunded, 100, deleted: true);
    $sell($this->variant, 3);

    $page = StorefrontPage::query()->where('workspace_id', $this->store->id)->where('slug', 'home')->firstOrFail();
    $page->update(['status' => 'published', 'published_at' => now(), 'published_content' => ['title' => 'Home', 'sections' => [
        ['id' => 1, 'type' => 'product_carousel', 'config' => ['source' => 'best-sellers', 'limit' => 24]],
    ]]]);
    $carousel = collect($this->getJson('/api/storefront/v1/cms/pages/home', $this->headers)->assertOk()->json('data.sections'))
        ->firstWhere('config.source', 'best-sellers');

    expect(array_column($carousel['data'], 'slug'))->toBe(['recent-hit', 'shirt', 'old-hit', 'quiet', 'refunded'])
        ->and(array_column($carousel['data'], 'sold_count'))->toBe([12, 0, 50, 0, 0])
        ->and(array_column($carousel['data'], 'is_best_seller'))->toBe([true, false, false, false, false]);
    $this->getJson('/api/storefront/v1/catalog/products/shirt', $this->headers)->assertOk()
        ->assertJsonPath('data.sold_count', 0)->assertJsonPath('data.is_best_seller', false);
});

it('orders home sections slider, new arrivals, top sellers, categories, then the rest', function (): void {
    [$headers, $data] = storefrontCheckoutFixture($this);
    $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated();
    $page = StorefrontPage::query()->where('workspace_id', $this->store->id)->where('slug', 'home')->firstOrFail();
    $page->update(['status' => 'published', 'published_at' => now(), 'published_content' => ['title' => 'Home', 'sections' => [
        ['id' => 1, 'type' => 'rich_text', 'config' => ['text' => 'Hi']],
        ['id' => 2, 'type' => 'category_grid', 'config' => []],
        ['id' => 3, 'type' => 'hero_slider', 'config' => ['slider_id' => 0]],
    ]]]);

    $sections = $this->getJson('/api/storefront/v1/cms/pages/home', $this->headers)->assertOk()->json('data.sections');

    expect(array_map(fn (array $s): string => $s['type'].':'.($s['config']['source'] ?? ''), $sections))
        ->toBe(['hero_slider:', 'product_carousel:new-arrivals', 'product_carousel:best-sellers', 'category_grid:', 'rich_text:']);
    expect($sections[2]['data'][0]['id'])->toBe($this->variant->product_id);
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

it('keeps the stock hold for an order whose payment proof awaits review', function (): void {
    Storage::fake('local');
    [$headers,$data] = storefrontCheckoutFixture($this);
    $order = $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data');
    expect($order['hold_expires_at'])->not->toBeNull();
    $this->post('/api/storefront/v1/orders/'.$order['submission_reference'].'/evidence', ['payment_method_id' => 'bank', 'transaction_id' => 'BANK-9', 'receipts' => [UploadedFile::fake()->image('receipt.png')]], $this->auth)->assertOk();
    $this->travel(25)->hours();
    app(OrderInventoryService::class)->expire();
    $model = Order::query()->findOrFail($order['id']);
    expect($model->status)->toBe('awaiting_payment')
        ->and($model->events()->where('key', 'hold_expired')->exists())->toBeFalse()
        ->and(OrderReservation::query()->where('order_id', $model->id)->where('state', 'reserved')->where('expires_at', '>', now())->exists())->toBeTrue()
        ->and($this->variant->fresh()->stock_quantity)->toBe(5);
});

it('cancels an unpaid order without payment proof when its stock hold expires', function (): void {
    [$headers,$data] = storefrontCheckoutFixture($this);
    $order = $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data');
    $this->travel(25)->hours();
    app(OrderInventoryService::class)->expire();
    $model = Order::query()->findOrFail($order['id']);
    expect($model->status)->toBe('cancelled')
        ->and($model->events()->where('key', 'hold_expired')->exists())->toBeTrue()
        ->and(OrderReservation::query()->where('order_id', $model->id)->where('state', 'reserved')->exists())->toBeFalse();
});

it('places a held order without choosing a payment method', function (): void {
    [$headers,$data] = storefrontCheckoutFixture($this);
    unset($data['payment_method_id']);
    $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertStatus(422);
    $order = $this->postJson('/api/storefront/v1/checkout/orders', [...$data, 'hold' => true], [...$headers, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data');
    expect($order['status'])->toBe('awaiting_payment')->and($order['hold_expires_at'])->not->toBeNull();
});

it('lets the owner extend a hold and reopen an order whose hold expired', function (): void {
    [$headers,$data] = storefrontCheckoutFixture($this);
    $order = Order::query()->findOrFail($this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data.id'));
    $inventory = app(OrderInventoryService::class);
    $before = $inventory->holdExpiresAt($order);
    $after = $inventory->extendHold($order, 48);
    expect($after->diffInHours($before, true))->toBeGreaterThanOrEqual(47.9);
    $this->travel(30)->hours();
    $inventory->expire();
    expect($order->fresh()->status)->toBe('awaiting_payment');
    $this->travel(50)->hours();
    $inventory->expire();
    expect($order->fresh()->status)->toBe('cancelled');
    app(OrderWorkflowService::class)->transition($order, 'awaiting_payment');
    expect($order->fresh()->status)->toBe('awaiting_payment')
        ->and($inventory->holdExpiresAt($order->fresh())?->isFuture())->toBeTrue();
});

it('lets the owner set an exact hold deadline', function (): void {
    [$headers,$data] = storefrontCheckoutFixture($this);
    $order = Order::query()->findOrFail($this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data.id'));
    $until = now()->addDays(5)->startOfMinute();
    app(OrderInventoryService::class)->holdUntil($order, $until);
    expect(app(OrderInventoryService::class)->holdExpiresAt($order)->equalTo($until))->toBeTrue();
});

it('does not reopen orders cancelled for other reasons', function (): void {
    [$headers,$data] = storefrontCheckoutFixture($this);
    $order = Order::query()->findOrFail($this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data.id'));
    app(OrderWorkflowService::class)->transition($order, 'cancelled');
    expect(fn () => app(OrderWorkflowService::class)->transition($order, 'awaiting_payment'))->toThrow(ValidationException::class);
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

function referralStore($test, array $settings = []): ReferralSetting
{
    $test->store->update(['settings' => [...(array) $test->store->settings, 'business_type' => 'referral']]);
    StoreFeatures::flush();

    return ReferralSetting::query()->updateOrCreate(['workspace_id' => $test->store->id], ['enabled' => true, 'cashin_enabled' => true, 'refunds_enabled' => true, 'reward_type' => 'fixed', 'reward_value' => 10, 'reward_scope' => 'every_order', 'withdrawal_frequency' => 'weekly', 'withdrawal_weekly_day' => 5, 'withdrawal_opens_at' => '09:00', 'withdrawal_closes_at' => '18:00', 'min_cashin_balance' => 5, 'max_cashin_amount' => 50, ...$settings]);
}

function storefrontCustomer($test, string $name, string $phone): array
{
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $test->store->id, 'name' => $name, 'phone' => $phone, 'source' => 'website', 'opt_in_status' => 'unknown']));
    $customer = app(CustomerService::class)->enroll($contact, true);

    return [$customer, [...$test->headers, 'Authorization' => 'Bearer '.$customer->createToken('test', ['storefront'], now()->addDay())->plainTextToken]];
}

it('keeps withdrawals and refund requests off for traditional stores', function (): void {
    ReferralSetting::query()->create(['workspace_id' => $this->store->id, 'enabled' => true, 'cashin_enabled' => true, 'refunds_enabled' => true]);
    app(RewardWalletService::class)->post($this->store->id, $this->customer->id, 800, 'adjustment');
    $this->getJson('/internal/stores/resolve?host=one.localhost', $this->headers)->assertOk()->assertJsonPath('data.store_type', 'traditional');
    $this->postJson('/api/storefront/v1/me/withdrawals', ['amount' => 5, 'method' => 'bkash', 'account_details' => '01700000000'], [...$this->auth, 'Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
    $this->getJson('/api/storefront/v1/me/withdrawals', $this->auth)->assertNotFound();
    $this->getJson('/api/storefront/v1/me/refund-requests', $this->auth)->assertNotFound();
    $this->getJson('/api/storefront/v1/me/rewards', $this->auth)->assertOk()->assertJsonPath('data.balance', '8.00');
    $this->getJson('/api/storefront/v1/referral/program', $this->headers)->assertOk()->assertJsonPath('data.withdrawals_enabled', false)->assertJsonPath('data.refunds_enabled', false);
    expect(app(RewardWalletService::class)->balance($this->store->id, $this->customer->id))->toBe(800);
    expect(fn () => app(RewardWalletService::class)->post($this->store->id, $this->customer->id, -900, 'redeem'))->toThrow(HttpException::class);
});

it('lets customers withdraw only inside the window and reserves the amount at once', function (): void {
    referralStore($this);
    $wallet = app(RewardWalletService::class);
    $wallet->post($this->store->id, $this->customer->id, 6000, 'adjustment');
    $this->getJson('/internal/stores/resolve?host=one.localhost', $this->headers)->assertOk()->assertJsonPath('data.store_type', 'referral');
    $body = ['amount' => '20', 'method' => 'bkash', 'account_details' => '01700000000'];
    $key = (string) Str::uuid();

    // Thursday in Dhaka: the weekly Friday window is closed.
    $this->travelTo(CarbonImmutable::parse('2026-11-19 12:00', 'Asia/Dhaka'));
    $this->auth = [...$this->headers, 'Authorization' => 'Bearer '.$this->customer->createToken('test', ['storefront'], now()->addDays(30))->plainTextToken];
    $this->getJson('/api/storefront/v1/me/withdrawals', $this->auth)->assertOk()->assertJsonPath('data.schedule.open', false)->assertJsonPath('data.schedule.next_opens_at', '2026-11-20T09:00:00+06:00')->assertJsonPath('data.balance', '60.00');
    $this->postJson('/api/storefront/v1/me/withdrawals', $body, [...$this->auth, 'Idempotency-Key' => $key])->assertStatus(422);
    expect($wallet->balance($this->store->id, $this->customer->id))->toBe(6000);

    // Friday 10:00 in Dhaka: open.
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Dhaka'));
    $this->postJson('/api/storefront/v1/me/withdrawals', [...$body, 'amount' => '2'], [...$this->auth, 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
    $this->postJson('/api/storefront/v1/me/withdrawals', [...$body, 'amount' => '55'], [...$this->auth, 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
    $this->postJson('/api/storefront/v1/me/withdrawals', [...$body, 'method' => 'paypal'], [...$this->auth, 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
    $id = $this->postJson('/api/storefront/v1/me/withdrawals', $body, [...$this->auth, 'Idempotency-Key' => $key])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
    $this->postJson('/api/storefront/v1/me/withdrawals', $body, [...$this->auth, 'Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.id', $id);
    expect($wallet->balance($this->store->id, $this->customer->id))->toBe(4000)
        ->and(CashinRequest::query()->count())->toBe(1);
    $this->getJson('/api/storefront/v1/me/withdrawals', $this->auth)->assertOk()->assertJsonPath('data.schedule.open', true)->assertJsonPath('data.balance', '40.00')->assertJsonPath('data.reserved', '20.00');

    // The reserved amount cannot be spent twice.
    $this->postJson('/api/storefront/v1/me/withdrawals', [...$body, 'amount' => '45'], [...$this->auth, 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);

    // Another customer cannot cancel it; the owner cancels nothing by repeating a decision.
    [, $strangerAuth] = storefrontCustomer($this, 'Stranger', '+8801700000007');
    $this->postJson("/api/storefront/v1/me/withdrawals/{$id}/cancel", [], $strangerAuth)->assertNotFound();

    // A later schedule change leaves the submitted request and its window untouched.
    $window = CashinRequest::query()->findOrFail($id)->window_starts_at;
    ReferralSetting::query()->where('workspace_id', $this->store->id)->update(['withdrawal_frequency' => 'monthly', 'withdrawal_monthly_day' => 1]);
    expect(CashinRequest::query()->findOrFail($id)->window_starts_at->equalTo($window))->toBeTrue()
        ->and(CashinRequest::query()->findOrFail($id)->status)->toBe('pending');

    $service = app(WithdrawalService::class);
    $service->decide($this->store->id, $id, 'rejected', 'Wrong number', null, 1);
    $service->decide($this->store->id, $id, 'rejected', 'Wrong number', null, 1);
    expect($wallet->balance($this->store->id, $this->customer->id))->toBe(6000);
    expect(fn () => $service->decide($this->store->id, $id, 'paid', null, 'TX1', 1))->toThrow(HttpException::class);
});

it('keeps the debit when a withdrawal is paid and releases it when the customer cancels', function (): void {
    referralStore($this, ['withdrawal_frequency' => 'monthly', 'withdrawal_monthly_day' => 31]);
    $wallet = app(RewardWalletService::class);
    $wallet->post($this->store->id, $this->customer->id, 6000, 'adjustment');
    // February has no 31st, so the window falls on the 28th.
    $this->travelTo(CarbonImmutable::parse('2027-02-28 09:30', 'Asia/Dhaka'));
    $this->auth = [...$this->headers, 'Authorization' => 'Bearer '.$this->customer->createToken('test', ['storefront'], now()->addDays(30))->plainTextToken];
    $body = ['amount' => '10', 'method' => 'bank', 'account_details' => 'Bank Asia 1234567'];
    $paid = $this->postJson('/api/storefront/v1/me/withdrawals', $body, [...$this->auth, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
    $cancelled = $this->postJson('/api/storefront/v1/me/withdrawals', $body, [...$this->auth, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
    expect($wallet->balance($this->store->id, $this->customer->id))->toBe(4000);

    $this->postJson("/api/storefront/v1/me/withdrawals/{$cancelled}/cancel", [], $this->auth)->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->postJson("/api/storefront/v1/me/withdrawals/{$cancelled}/cancel", [], $this->auth)->assertOk();
    expect($wallet->balance($this->store->id, $this->customer->id))->toBe(5000);

    $service = app(WithdrawalService::class);
    $service->decide($this->store->id, $paid, 'processing', null, null, 1);
    $service->decide($this->store->id, $paid, 'paid', null, 'TX-99', 1);
    $this->postJson("/api/storefront/v1/me/withdrawals/{$paid}/cancel", [], $this->auth)->assertStatus(422);
    expect($wallet->balance($this->store->id, $this->customer->id))->toBe(5000)
        ->and(RewardLedgerEntry::query()->where('customer_id', $this->customer->id)->pluck('type')->all())->toBe(['adjustment', 'cashin_hold', 'cashin_hold', 'cashin_refund']);
    $this->getJson('/api/storefront/v1/me/withdrawals', $this->auth)->assertOk()->assertJsonPath('data.requests.1.payout_reference', 'TX-99');
});

it('sends a refund request to the referrer first and lets only the owner complete it', function (): void {
    referralStore($this);
    $wallet = app(RewardWalletService::class);
    $refunds = app(RefundRequestService::class);
    $referrer = $this->customer;
    $referrerAuth = $this->auth;
    $code = app(ReferralService::class)->codeFor($referrer)->code;
    [$friend, $friendAuth] = storefrontCustomer($this, 'Friend', '+8801700000009');
    [, $strangerAuth] = storefrontCustomer($this, 'Stranger', '+8801700000007');
    app(ReferralService::class)->attach($friend, $code);

    $this->customer = $friend;
    $this->auth = $friendAuth;
    [$headers, $data] = storefrontCheckoutFixture($this);
    // The checkout fixture rewrites the workspace settings, so the store type is set again.
    referralStore($this);
    $order = Order::query()->findOrFail($this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data.id'));
    $ref = $order->submission_reference;

    // Unpaid orders cannot be refunded.
    $this->getJson("/api/storefront/v1/me/orders/{$ref}/refund-eligibility", $friendAuth)->assertOk()->assertJsonPath('data.eligible', false);
    app(OrderWorkflowService::class)->transition($order, 'paid');
    $order->refresh()->update(['status' => 'shipped']);
    app(OrderWorkflowService::class)->transition($order, 'completed');
    expect($wallet->balance($this->store->id, $referrer->id))->toBe(1000);
    $this->getJson("/api/storefront/v1/me/orders/{$ref}/refund-eligibility", $friendAuth)->assertOk()->assertJsonPath('data.eligible', true)->assertJsonPath('data.refundable', '45.00');

    $key = (string) Str::uuid();
    $body = ['order' => $ref, 'amount' => '45', 'reason' => 'damaged', 'details' => 'Torn sleeve'];
    $this->postJson('/api/storefront/v1/me/refund-requests', [...$body, 'amount' => '50'], [...$friendAuth, 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
    $this->postJson('/api/storefront/v1/me/refund-requests', $body, [...$strangerAuth, 'Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
    $reference = $this->postJson('/api/storefront/v1/me/refund-requests', $body, [...$friendAuth, 'Idempotency-Key' => $key])->assertCreated()->assertJsonPath('data.status', 'pending_referrer')->json('data.reference');
    $this->postJson('/api/storefront/v1/me/refund-requests', $body, [...$friendAuth, 'Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.reference', $reference);
    $this->postJson('/api/storefront/v1/me/refund-requests', $body, [...$friendAuth, 'Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
    expect(RefundRequest::query()->count())->toBe(1);

    // Customers see only their own requests; referrers see only requests from people they referred.
    $this->getJson("/api/storefront/v1/me/refund-requests/{$reference}", $friendAuth)->assertOk();
    $this->getJson("/api/storefront/v1/me/refund-requests/{$reference}", $strangerAuth)->assertNotFound();
    $this->getJson("/api/storefront/v1/me/refund-requests/{$reference}", $referrerAuth)->assertNotFound();
    $this->getJson("/api/storefront/v1/me/referral/refund-requests/{$reference}", $strangerAuth)->assertNotFound();
    $this->getJson("/api/storefront/v1/me/referral/refund-requests/{$reference}", $friendAuth)->assertNotFound();
    $this->postJson("/api/storefront/v1/me/referral/refund-requests/{$reference}/decision", ['decision' => 'approved'], $strangerAuth)->assertNotFound();
    $this->getJson('/api/storefront/v1/me/referral/refund-requests?status=pending', $referrerAuth)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_decide', true)->assertJsonPath('data.0.customer.name', 'Friend');

    // The referrer's approval moves no money.
    $this->postJson("/api/storefront/v1/me/referral/refund-requests/{$reference}/decision", ['decision' => 'rejected'], $referrerAuth)->assertStatus(422);
    $this->postJson("/api/storefront/v1/me/referral/refund-requests/{$reference}/decision", ['decision' => 'approved', 'comment' => 'Seen the photos'], $referrerAuth)->assertOk()->assertJsonPath('data.status', 'awaiting_admin');
    $this->postJson("/api/storefront/v1/me/referral/refund-requests/{$reference}/decision", ['decision' => 'rejected', 'comment' => 'Changed my mind'], $referrerAuth)->assertStatus(422);
    expect($wallet->balance($this->store->id, $referrer->id))->toBe(1000)
        ->and($order->fresh()->payment_state)->toBe('paid');

    // The owner completes it once: wallet credit, order payment state and commission reversal.
    $request = RefundRequest::query()->where('reference', $reference)->firstOrFail();
    expect(fn () => $refunds->adminTransition($this->store->id, $request->id, 'completed', ['payout_method' => 'wallet'], 1))->toThrow(HttpException::class);
    expect(fn () => $refunds->adminTransition($this->other->id, $request->id, 'processing', [], 1))->toThrow(ModelNotFoundException::class);
    $refunds->adminTransition($this->store->id, $request->id, 'processing', [], 1);
    $refunds->adminTransition($this->store->id, $request->id, 'completed', ['payout_method' => 'wallet'], 1);
    $refunds->adminTransition($this->store->id, $request->id, 'completed', ['payout_method' => 'wallet'], 1);
    expect($wallet->balance($this->store->id, $friend->id))->toBe(4500)
        ->and($wallet->balance($this->store->id, $referrer->id))->toBe(0)
        ->and($order->fresh()->payment_state)->toBe('refunded')
        ->and($order->fresh()->refunded_amount)->toEqual('45.0000')
        ->and($request->fresh()->commission_reversed_minor)->toBe(1000)
        ->and($request->fresh()->commission_shortfall_minor)->toBe(0)
        ->and($request->events()->pluck('to_status')->all())->toBe(['pending_referrer', 'awaiting_admin', 'processing', 'completed'])
        ->and(RewardLedgerEntry::query()->where('type', 'order_refund')->count())->toBe(1)
        ->and(RewardLedgerEntry::query()->where('type', 'referral_reversal')->count())->toBe(1);
    $this->getJson("/api/storefront/v1/me/orders/{$ref}/refund-eligibility", $friendAuth)->assertOk()->assertJsonPath('data.eligible', false);
    expect(fn () => $request->events()->first()->update(['comment' => 'edited']))->toThrow(LogicException::class);
});

it('records the customer of a rejected refund and a shortfall when the referrer already spent the commission', function (): void {
    referralStore($this);
    $wallet = app(RewardWalletService::class);
    $refunds = app(RefundRequestService::class);
    $referrer = $this->customer;
    $referrerAuth = $this->auth;
    [$friend, $friendAuth] = storefrontCustomer($this, 'Friend', '+8801700000009');
    app(ReferralService::class)->attach($friend, app(ReferralService::class)->codeFor($referrer)->code);
    $this->customer = $friend;
    $this->auth = $friendAuth;
    [$headers, $data] = storefrontCheckoutFixture($this);
    // The checkout fixture rewrites the workspace settings, so the store type is set again.
    referralStore($this);
    $order = Order::query()->findOrFail($this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data.id'));
    app(OrderWorkflowService::class)->transition($order, 'paid');
    $order->refresh()->update(['status' => 'shipped']);
    app(OrderWorkflowService::class)->transition($order, 'completed');
    $wallet->post($this->store->id, $referrer->id, -700, 'adjustment');

    $body = ['order' => $order->submission_reference, 'amount' => '22.50', 'reason' => 'other'];
    $first = $this->postJson('/api/storefront/v1/me/refund-requests', $body, [...$friendAuth, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.reference');
    $this->postJson("/api/storefront/v1/me/referral/refund-requests/{$first}/decision", ['decision' => 'rejected', 'comment' => 'Item was fine'], $referrerAuth)->assertOk()->assertJsonPath('data.status', 'referrer_rejected');
    $this->getJson("/api/storefront/v1/me/refund-requests/{$first}", $friendAuth)->assertOk()->assertJsonPath('data.referrer_comment', 'Item was fine');
    expect($order->fresh()->payment_state)->toBe('paid');

    // A rejected request is closed, so the customer may ask again; half the order is refunded by hand.
    $second = $this->postJson('/api/storefront/v1/me/refund-requests', $body, [...$friendAuth, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.reference');
    $request = RefundRequest::query()->where('reference', $second)->firstOrFail();
    $refunds->adminTransition($this->store->id, $request->id, 'processing', [], 1);
    $refunds->adminTransition($this->store->id, $request->id, 'completed', ['payout_method' => 'manual', 'payout_channel' => 'bkash', 'payout_reference' => 'TX5'], 1);
    expect($order->fresh()->payment_state)->toBe('partially_refunded')
        ->and($wallet->balance($this->store->id, $friend->id))->toBe(0)
        ->and($wallet->balance($this->store->id, $referrer->id))->toBe(0)
        ->and($request->fresh()->commission_reversed_minor)->toBe(300)
        ->and($request->fresh()->commission_shortfall_minor)->toBe(200)
        ->and($request->fresh()->payout_reference)->toBe('TX5');
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

function enableMetaCapi($test, array $extra = []): void
{
    app(TrackingSettingsService::class)->save($test->store->id, ['meta_pixel_id' => '123456789012', 'meta_capi_enabled' => true, 'meta_capi_token' => 'EAAB-secret', ...$extra]);
}

it('waits for payment confirmation before queueing one Purchase per order', function (): void {
    Queue::fake();
    [$headers, $data] = storefrontCheckoutFixture($this);
    $headers = [...$headers, 'X-Client-Ip' => '203.0.113.9', 'X-Client-User-Agent' => 'Mozilla/5.0', 'X-Meta-Fbp' => 'fb.1.1710000000.123', 'X-Meta-Fbc' => 'not valid', 'X-Event-Source-Url' => 'https://evil.example/checkout'];

    $created = $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data');
    $this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertOk();
    $order = Order::query()->findOrFail($created['id']);

    Queue::assertNotPushed(SendMetaPurchaseEvent::class);
    Queue::assertNotPushed(SendTikTokPurchaseEvent::class);
    expect(MetaConversionsService::rememberedClient($order))->toMatchArray(['ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0', 'fbp' => 'fb.1.1710000000.123', 'fbc' => null, 'source_url' => null, 'origin' => 'web']);

    app(OrderWorkflowService::class)->transition($order, 'paid');
    app(OrderWorkflowService::class)->transition($order->fresh(), 'paid');
    app(UnifiedOrderService::class)->event($order->fresh(), 'paid', 'Payment confirmed');

    Queue::assertPushed(SendMetaPurchaseEvent::class, 1);
    Queue::assertPushed(SendTikTokPurchaseEvent::class, 1);
    Queue::assertPushed(SendMetaPurchaseEvent::class, fn (SendMetaPurchaseEvent $job): bool => $job->orderId === $order->id && $job->client === []);
    expect($this->getJson('/api/storefront/v1/me/orders/'.$order->submission_reference, $this->auth)->assertOk()->json('data.paid_at'))->not->toBeNull();
});

it('sends the paid Purchase once with the checkout browser details and never for unpaid orders', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
    $this->mock(WhatsAppSettingsService::class, fn ($mock) => $mock->shouldReceive('graphApiVersion')->andReturn('v24.0'));
    enableMetaCapi($this);
    [$headers, $data] = storefrontCheckoutFixture($this);
    $headers = [...$headers, 'X-Client-Ip' => '203.0.113.9', 'X-Meta-Fbp' => 'fb.1.1710000000.123', 'X-Event-Source-Url' => 'https://one.localhost/en/checkout'];
    $order = Order::query()->findOrFail($this->postJson('/api/storefront/v1/checkout/orders', $data, $headers)->assertCreated()->json('data.id'));

    (new SendMetaPurchaseEvent($order->id))->handle(app(MetaConversionsService::class));
    Http::assertNothingSent();

    app(OrderWorkflowService::class)->transition($order, 'paid');
    (new SendMetaPurchaseEvent($order->id))->handle(app(MetaConversionsService::class));
    (new SendMetaPurchaseEvent($order->id))->handle(app(MetaConversionsService::class));

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['data'][0]['event_name'] === 'Purchase'
        && $request['data'][0]['event_id'] === 'purchase-'.$order->number
        && $request['data'][0]['action_source'] === 'website'
        && $request['data'][0]['event_source_url'] === 'https://one.localhost/en/checkout'
        && $request['data'][0]['user_data']['client_ip_address'] === '203.0.113.9'
        && $request['data'][0]['user_data']['fbp'] === 'fb.1.1710000000.123'
        && $request['data'][0]['custom_data']['value'] === 45.0
        && $request['data'][0]['custom_data']['currency'] === 'USD'
        && $request['data'][0]['custom_data']['content_ids'] === ['shirt-m']);
});

it('pairs AddToCart, ViewContent, InitiateCheckout and PageView with the browser event id', function (): void {
    Queue::fake();
    enableMetaCapi($this);
    $client = [...$this->headers, 'X-Meta-Event-Id' => 'evt_add_123456', 'X-Client-Ip' => '203.0.113.9', 'X-Event-Source-Url' => 'https://one.localhost/en/products/shirt'];
    $token = $this->postJson('/api/storefront/v1/cart', [], $this->headers)->assertCreated()->json('data.cart_token');

    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 9], [...$client, 'X-Cart-Token' => $token])->assertUnprocessable();
    Queue::assertNotPushed(SendMetaEvent::class);

    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 2], [...$client, 'X-Cart-Token' => $token])->assertOk();
    $this->postJson('/api/storefront/v1/track/view-content', ['product_id' => $this->product->id], [...$client, 'X-Meta-Event-Id' => 'evt_view_123456'])->assertNoContent();
    $this->postJson('/api/storefront/v1/track/initiate-checkout', [], [...$client, 'X-Meta-Event-Id' => 'evt_checkout_123', 'X-Cart-Token' => $token])->assertNoContent();
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => (string) Str::uuid(), 'path' => '/en'], [...$client, 'X-Meta-Event-Id' => 'evt_page_1234567'])->assertNoContent();

    $events = collect(Queue::pushed(SendMetaEvent::class))->mapWithKeys(fn (SendMetaEvent $job): array => [$job->event['event_name'] => $job->event]);
    expect($events->keys()->all())->toEqualCanonicalizing(['AddToCart', 'ViewContent', 'InitiateCheckout', 'PageView'])
        ->and($events['AddToCart'])->toMatchArray(['event_id' => 'evt_add_123456', 'action_source' => 'website', 'event_source_url' => 'https://one.localhost/en/products/shirt'])
        ->and($events['AddToCart']['custom_data'])->toMatchArray(['currency' => 'USD', 'value' => 40.0, 'content_type' => 'product', 'content_ids' => ['shirt-m'], 'num_items' => 2])
        ->and($events['ViewContent']['custom_data'])->toMatchArray(['content_type' => 'product_group', 'content_ids' => ['product-'.$this->product->id], 'value' => 20.0, 'content_name' => 'Shirt'])
        ->and($events['InitiateCheckout']['custom_data'])->toMatchArray(['value' => 40.0, 'num_items' => 2, 'content_ids' => ['shirt-m']])
        ->and($events['PageView']['event_id'])->toBe('evt_page_1234567')
        ->and($events['PageView']['user_data']['client_ip_address'])->toBe('203.0.113.9');
    $this->getJson('/api/storefront/v1/cart', [...$this->headers, 'X-Cart-Token' => $token])->assertOk()->assertJsonPath('data.items.0.retailer_id', 'shirt-m');
    $this->getJson('/api/storefront/v1/catalog/products/shirt', $this->headers)->assertOk()->assertJsonPath('data.variants.0.retailer_id', 'shirt-m');
});

it('skips server events without consent, without CAPI, or from the mobile app', function (): void {
    Queue::fake();
    $page = fn (array $headers, string $prefix = '/api/storefront/v1') => $this->postJson($prefix.'/track/page-view', ['visitor_key' => (string) Str::uuid(), 'path' => '/'], [...$headers, 'X-Meta-Event-Id' => 'evt_page_'.Str::random(8)])->assertNoContent();

    $page($this->headers);
    enableMetaCapi($this, ['consent_required' => true]);
    $page($this->headers);
    $page([...$this->headers, 'X-Tracking-Consent' => 'denied']);
    $page(['X-Store-Host' => 'one.localhost', 'X-Tracking-Consent' => 'granted'], '/api/v1/mobile/storefront');
    Queue::assertNotPushed(SendMetaEvent::class);

    $page([...$this->headers, 'X-Tracking-Consent' => 'granted']);
    Queue::assertPushed(SendMetaEvent::class, 1);
    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)->assertOk()->assertJsonPath('data.tracking.consent_required', true);
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

it('refuses to add or raise cart quantities beyond available stock', function (): void {
    $h = [...$this->headers, 'X-Cart-Token' => $this->postJson('/api/storefront/v1/cart', [], $this->headers)->assertCreated()->json('data.cart_token')];

    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 6], $h)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'out_of_stock')
        ->assertJsonPath('available', 5);

    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 4], $h)->assertOk();
    $itemId = $this->getJson('/api/storefront/v1/cart', $h)->json('data.items.0.id');
    $this->patchJson('/api/storefront/v1/cart/items/'.$itemId, ['quantity' => 6], $h)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'out_of_stock');
    $this->patchJson('/api/storefront/v1/cart/items/'.$itemId, ['quantity' => 5], $h)->assertOk();
});

it('refuses to add a variant that is out of stock and reports it in the catalog', function (): void {
    $h = [...$this->headers, 'X-Cart-Token' => $this->postJson('/api/storefront/v1/cart', [], $this->headers)->assertCreated()->json('data.cart_token')];
    $this->variant->update(['stock_quantity' => 0, 'status' => 'out_of_stock']);

    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 1], $h)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'out_of_stock')
        ->assertJsonPath('available', 0);

    $this->getJson('/api/storefront/v1/catalog/products/shirt', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.in_stock', false)
        ->assertJsonPath('data.stock_status', 'out_of_stock')
        ->assertJsonPath('data.variants.0.in_stock', false)
        ->assertJsonPath('data.variants.0.available_quantity', 0);
});

it('counts units held by open reservations as unavailable', function (): void {
    $order = Order::query()->create(['workspace_id' => $this->store->id, 'contact_id' => $this->contact->id, 'source' => 'storefront', 'submission_reference' => (string) Str::uuid(), 'payload_hash' => 'x', 'number' => 'ORD-S1', 'tracking_code' => 'TRK-S1', 'status' => 'pending', 'currency' => 'USD', 'subtotal' => 20, 'total' => 20, 'customer_snapshot' => [], 'shipping_address' => []]);
    OrderReservation::query()->create(['order_id' => $order->id, 'variant_id' => $this->variant->id, 'quantity' => 4, 'state' => 'reserved', 'generation' => 1, 'expires_at' => now()->addHour(), 'operation_key' => 'test:reserve']);

    $this->getJson('/api/storefront/v1/catalog/products/shirt', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.in_stock', true)
        ->assertJsonPath('data.variants.0.available_quantity', 1);

    $h = [...$this->headers, 'X-Cart-Token' => $this->postJson('/api/storefront/v1/cart', [], $this->headers)->assertCreated()->json('data.cart_token')];
    $this->postJson('/api/storefront/v1/cart/items', ['variant_id' => $this->variant->id, 'quantity' => 2], $h)
        ->assertUnprocessable()
        ->assertJsonPath('available', 1);
});

it('looks up postal codes for the storefront and validates international phone numbers', function (): void {
    Http::fake(['*zippopotam*' => Http::response(['places' => [['place name' => 'Gulshan Model Town ', 'state' => 'Dhaka', 'state abbreviation' => '']]])]);

    $this->getJson('/api/storefront/v1/geo/postal-lookup?country=BD&postal_code=1212', $this->headers)->assertOk()
        ->assertJsonPath('data.city', 'Dhaka')->assertJsonPath('data.area', 'Gulshan Model Town')->assertJsonPath('data.postal_code', '1212');
    $this->getJson('/api/storefront/v1/geo/postal-lookup?country=FR&postal_code=75001', $this->headers)->assertUnprocessable();

    $binding = ['session_binding' => str_repeat('b', 32), 'consent' => true];
    $this->postJson('/api/storefront/v1/auth/whatsapp/challenges', ['phone' => '+1', ...$binding], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('phone');
    $this->postJson('/api/storefront/v1/auth/password/login', ['phone' => 'not a number', 'password' => 'x'], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('phone');
});

it('lists only published pages and blogs in the cms sitemap', function (): void {
    StorefrontPage::query()->where('workspace_id', $this->store->id)->where('slug', 'home')->update(['status' => 'published']);
    StorefrontPage::query()->create(['workspace_id' => $this->store->id, 'slug' => 'draft-page', 'title' => 'Draft', 'status' => 'draft']);
    $slugs = $this->getJson('/api/storefront/v1/cms/sitemap', $this->headers)->assertOk()->json('data.pages.*.slug');
    expect($slugs)->toContain('home')->not->toContain('draft-page');
});

function ratingReviewer($test, string $phone): Customer
{
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $test->store->id, 'name' => 'Reviewer '.$phone, 'phone' => $phone, 'source' => 'website', 'opt_in_status' => 'unknown']));

    return app(CustomerService::class)->enroll($contact, true);
}

it('derives product rating and reviews_count from approved reviews only', function (): void {
    $url = '/api/storefront/v1/catalog/products/shirt';
    $this->getJson($url, $this->headers)->assertOk()
        ->assertJsonPath('data.rating', 0)
        ->assertJsonPath('data.reviews_count', 0);

    $five = ProductReview::query()->create(['workspace_id' => $this->store->id, 'customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'rating' => 5, 'body' => 'Great', 'status' => 'pending']);
    $four = ProductReview::query()->create(['workspace_id' => $this->store->id, 'customer_id' => ratingReviewer($this, '+8801700000001')->id, 'product_id' => $this->product->id, 'rating' => 4, 'body' => 'Good', 'status' => 'pending']);
    expect($this->product->fresh()->reviews_count)->toBe(0);

    $five->update(['status' => 'approved']);
    $four->update(['status' => 'approved']);
    $this->getJson($url, $this->headers)->assertOk()
        ->assertJsonPath('data.rating', 4.5)
        ->assertJsonPath('data.reviews_count', 2);

    $four->update(['status' => 'rejected']);
    expect((float) $this->product->fresh()->rating)->toBe(5.0)
        ->and($this->product->fresh()->reviews_count)->toBe(1);

    $five->delete();
    expect((float) $this->product->fresh()->rating)->toBe(0.0)
        ->and($this->product->fresh()->reviews_count)->toBe(0);
});

it('backfills fake rating defaults with the refresh-ratings command', function (): void {
    Product::query()->whereKey($this->product->id)->update(['rating' => 5.00, 'reviews_count' => 128]);
    ProductReview::withoutEvents(fn () => ProductReview::query()->create(['workspace_id' => $this->store->id, 'customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'rating' => 3, 'body' => 'Ok', 'status' => 'approved']));

    $this->artisan('commerce:refresh-ratings')->assertSuccessful();

    expect((float) $this->product->fresh()->rating)->toBe(3.0)
        ->and($this->product->fresh()->reviews_count)->toBe(1);
});

it('treats a carousel titled Top Sellers as the top-sellers slot instead of adding a duplicate', function (): void {
    $page = StorefrontPage::query()->where('workspace_id', $this->store->id)->where('slug', 'home')->firstOrFail();
    $page->update(['status' => 'published', 'published_at' => now(), 'published_content' => ['title' => 'Home', 'sections' => [
        ['id' => 1, 'type' => 'category_grid', 'config' => []],
        ['id' => 2, 'type' => 'product_carousel', 'config' => ['source' => 'featured', 'title' => 'TOP SELLERS']],
        ['id' => 3, 'type' => 'product_carousel', 'config' => ['source' => 'manual', 'title' => 'New  Arrivals']],
    ]]]);

    $sections = $this->getJson('/api/storefront/v1/cms/pages/home', $this->headers)->assertOk()->json('data.sections');

    expect(array_map(fn (array $s): string => $s['type'].':'.($s['config']['title'] ?? ''), $sections))
        ->toBe(['product_carousel:New  Arrivals', 'product_carousel:TOP SELLERS', 'category_grid:']);
});

it('rate limits server-rendered storefront calls per forwarded visitor IP', function (): void {
    $limiter = RateLimiter::limiter('storefront');
    $make = function (?string $client): Request {
        $r = Request::create('/api/storefront/v1/catalog/products', 'GET', server: ['REMOTE_ADDR' => '10.0.0.5']);
        $r->headers->set('X-Store-Host', 'one.localhost');
        if ($client !== null) {
            $r->headers->set('X-Storefront-Client-IP', $client);
        }
        $r->attributes->set('storefront_platform', true);

        return $r;
    };

    $a = $limiter($make('203.0.113.10'));
    $b = $limiter($make('203.0.113.20'));
    $server = $limiter($make(null));

    expect($a->maxAttempts)->toBe(600)
        ->and($a->key)->not->toBe($b->key)
        ->and($server->maxAttempts)->toBe(3000);

    // The real middleware marks platform calls, so a forwarded IP request passes through normally.
    $this->getJson('/api/storefront/v1/catalog/products', [...$this->headers, 'X-Storefront-Client-IP' => '203.0.113.10'])->assertOk();
});

it('ignores a client IP header on mobile routes when rate limiting', function (): void {
    $limiter = RateLimiter::limiter('storefront');
    $request = Request::create('/api/v1/mobile/storefront/catalog/products', 'GET', server: ['REMOTE_ADDR' => '198.51.100.7']);
    $request->headers->set('X-Store-Host', 'one.localhost');
    $request->headers->set('X-Storefront-Client-IP', '203.0.113.99');
    $limit = $limiter($request);

    expect($limit->maxAttempts)->toBe(240)
        ->and($limit->key)->toBe(hash('sha256', 'one.localhost:198.51.100.7'));

    $request->attributes->set('storefront_platform', true);
    expect($limiter($request)->maxAttempts)->toBe(600);
});

it('exposes the size chart for sizes the product still sells', function (): void {
    $option = $this->product->options()->create(['workspace_id' => $this->store->id, 'name' => 'Size', 'code' => 'size', 'position' => 0]);
    foreach (['M', 'S'] as $position => $value) {
        $option->values()->create(['workspace_id' => $this->store->id, 'value' => $value, 'position' => $position]);
    }
    $this->product->update(['size_chart' => [
        'unit' => 'cm',
        'columns' => ['Chest', 'Length'],
        'rows' => [['size' => 'S', 'values' => ['96', '70']], ['size' => 'XL', 'values' => ['110', '76']], ['size' => 'M', 'values' => ['100', '72']]],
        'note' => 'Measured flat',
    ]]);

    $this->getJson('/api/storefront/v1/catalog/products/shirt', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.size_chart.columns', ['Chest', 'Length'])
        ->assertJsonPath('data.size_chart.rows', [['size' => 'M', 'values' => ['100', '72']], ['size' => 'S', 'values' => ['96', '70']]])
        ->assertJsonPath('data.size_chart.note', 'Measured flat');
});

it('lets customers wishlist and read reviews of wholesale-only products', function (): void {
    $pack = Product::query()->create(['workspace_id' => $this->store->id, 'name' => 'Bulk Tee', 'slug' => 'bulk-tee', 'sku' => 'bulk-tee', 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'wholesale', 'ws_enabled' => true, 'single_piece_price' => 20]);

    $this->postJson("/api/storefront/v1/me/wishlist/{$pack->id}", [], $this->auth)
        ->assertOk()
        ->assertJsonPath('data.0.id', $pack->id);
    $this->getJson('/api/storefront/v1/catalog/products/bulk-tee/reviews', $this->headers)->assertOk();
});

it('ignores a forwarded client IP on mobile OTP requests', function (): void {
    $this->mock(WhatsAppCustomerAuthService::class, function ($mock): void {
        $mock->shouldReceive('challenge')->once()
            ->withArgs(fn (int $workspace, array $data): bool => $data['client_ip'] === '127.0.0.1')
            ->andReturn(['id' => 'challenge']);
    });

    $this->postJson('/api/v1/mobile/storefront/auth/whatsapp/challenges', [
        'phone' => '+8801711111111',
        'session_binding' => str_repeat('s', 40),
        'consent' => true,
    ], ['X-Store-Host' => 'one.localhost', 'X-Storefront-Client-IP' => '203.0.113.5'])->assertOk();
});

it('caches cms settings per workspace without querying Meta tables and invalidates on updates', function (): void {
    StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->store->id], ['config' => ['name' => 'Before Cache']]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.name', 'Before Cache');
    $firstQueries = collect(DB::getQueryLog())->pluck('query');
    expect($firstQueries->contains(fn (string $sql): bool => str_contains($sql, 'meta_business_connections') || str_contains($sql, 'channel_accounts')))->toBeFalse();

    DB::flushQueryLog();
    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.name', 'Before Cache');
    $secondQueries = collect(DB::getQueryLog())->pluck('query');
    expect($secondQueries->contains(fn (string $sql): bool => str_contains($sql, 'commerce_storefront_settings')))->toBeFalse();
    DB::disableQueryLog();

    StorefrontSetting::query()->updateOrCreate(['workspace_id' => $this->store->id], ['config' => ['name' => 'After Save']]);
    $this->getJson('/api/storefront/v1/cms/settings', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.name', 'After Save');
});

function pushServiceAccount(string $project = 'fashion-fiest-test'): string
{
    return json_encode(['type' => 'service_account', 'project_id' => $project, 'private_key' => "-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----\n", 'client_email' => 'firebase-adminsdk@'.$project.'.iam.gserviceaccount.com'], JSON_THROW_ON_ERROR);
}

function pushSettings(array $overrides = []): array
{
    return ['enabled' => true, 'project_id' => 'fashion-fiest-test', 'api_key' => 'AIzaTestKey', 'messaging_sender_id' => '123456789012', 'android_app_id' => '1:123456789012:android:abc123', 'ios_app_id' => '', 'ios_bundle_id' => '', 'service_account' => pushServiceAccount(), ...$overrides];
}

it('exposes only public firebase identifiers to the store app and keeps the key encrypted', function (): void {
    $this->getJson('/api/v1/mobile/storefront/cms/push-config', ['X-Store-Host' => 'one.localhost'])
        ->assertOk()->assertExactJson(['data' => ['enabled' => false]]);

    $service = app(StorePushService::class);
    $settings = $service->save($this->store->id, pushSettings());

    expect($settings['ready'])->toBeTrue()
        ->and($settings['service_account_email'])->toBe('firebase-adminsdk@fashion-fiest-test.iam.gserviceaccount.com')
        ->and($settings)->not->toHaveKey('service_account');
    $stored = StorefrontSetting::query()->where('workspace_id', $this->store->id)->value('config');
    expect(json_encode($stored))->not->toContain('PRIVATE KEY');

    $this->getJson('/api/v1/mobile/storefront/cms/push-config', ['X-Store-Host' => 'one.localhost'])
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.project_id', 'fashion-fiest-test')
        ->assertJsonPath('data.android_app_id', '1:123456789012:android:abc123')
        ->assertJsonMissingPath('data.service_account');

    $service->save($this->store->id, pushSettings(['service_account' => '']));
    expect($service->settings($this->store->id)['service_account_email'])->toBe('firebase-adminsdk@fashion-fiest-test.iam.gserviceaccount.com');
});

it('rejects a service account from another firebase project or an incomplete setup', function (): void {
    $service = app(StorePushService::class);

    expect(fn () => $service->save($this->store->id, pushSettings(['service_account' => pushServiceAccount('other-project')])))
        ->toThrow(ValidationException::class);
    expect(fn () => $service->save($this->store->id, pushSettings(['android_app_id' => '', 'ios_app_id' => ''])))
        ->toThrow(ValidationException::class);
    expect(fn () => $service->save($this->store->id, pushSettings(['service_account' => '{"type":"user"}'])))
        ->toThrow(ValidationException::class);
});

it('registers and removes the signed-in customer device for push', function (): void {
    $this->postJson('/api/v1/mobile/storefront/me/devices', ['token' => 'fcm-customer', 'platform' => 'android'], ['X-Store-Host' => 'one.localhost', 'Authorization' => $this->auth['Authorization']])
        ->assertOk();
    expect(MobileCustomerDevice::query()->where('token', 'fcm-customer')->value('customer_reference'))
        ->toBe('storefront:'.$this->customer->id);

    $this->deleteJson('/api/v1/mobile/storefront/me/devices', ['token' => 'fcm-customer'], ['X-Store-Host' => 'one.localhost', 'Authorization' => $this->auth['Authorization']])
        ->assertOk();
    expect(MobileCustomerDevice::query()->where('token', 'fcm-customer')->exists())->toBeFalse();

    $this->postJson('/api/v1/mobile/storefront/me/devices', ['token' => 'fcm-guest', 'platform' => 'android'], ['X-Store-Host' => 'one.localhost'])
        ->assertUnauthorized();
});

it('sends mobile alerts through the store firebase project and skips stores without push', function (): void {
    $device = MobileCustomerDevice::query()->create(['workspace_id' => $this->store->id, 'customer_reference' => 'storefront:'.$this->customer->id, 'token' => 'fcm-1', 'platform' => 'android']);
    $alert = new MobileAlert('Shipped', 'On its way', ['type' => 'order'], $this->store->id);

    expect($alert->via($device))->toBe([]);

    app(StorePushService::class)->save($this->store->id, pushSettings());
    $messaging = Mockery::mock(Messaging::class);
    $push = Mockery::mock(StorePushService::class)->makePartial();
    $push->shouldReceive('messaging')->with($this->store->id)->andReturn($messaging);
    $this->app->instance(StorePushService::class, $push);

    expect($alert->via($device))->toBe([FcmChannel::class])
        ->and($alert->toFcm($device)->client)->toBe($messaging)
        ->and($alert->toFcm($device)->data['workspace_id'])->toBe((string) $this->store->id);
});
