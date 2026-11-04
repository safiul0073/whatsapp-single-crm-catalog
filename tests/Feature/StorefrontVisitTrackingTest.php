<?php

use App\Models\User;
use App\Modules\Commerce\Storefront\Jobs\ResolveVisitorGeo;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontPageView;
use App\Modules\Commerce\Storefront\Models\StorefrontVisitor;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'storefront.platform_secret' => str_repeat('p', 40), 'cache.default' => 'array']);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Queue::fake();
    $this->store = Workspace::query()->create(['name' => 'One', 'slug' => 'one', 'status' => 'active']);
    $this->other = Workspace::query()->create(['name' => 'Two', 'slug' => 'two', 'status' => 'active']);
    foreach ([$this->store, $this->other] as $w) {
        StoreDomain::query()->create(['workspace_id' => $w->id, 'host' => $w->slug.'.localhost', 'is_primary' => true, 'status' => 'active', 'verification_token' => 'test', 'type' => 'system']);
    }
    $this->headers = ['X-Storefront-Secret' => str_repeat('p', 40), 'X-Store-Host' => 'one.localhost', 'X-Client-Ip' => '8.8.8.8', 'X-Client-Country' => 'BD', 'X-Client-User-Agent' => 'Mozilla/5.0 (iPhone; Mobile)'];
    $this->key = (string) Str::uuid();
});

afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('records an anonymous visit with ip, country and device', function (): void {
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/en/products'], $this->headers)->assertNoContent();
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/en/cart'], $this->headers)->assertNoContent();

    $visitor = StorefrontVisitor::query()->sole();
    expect($visitor->workspace_id)->toBe($this->store->id)
        ->and($visitor->ip)->toBe('8.8.8.8')
        ->and($visitor->country_code)->toBe('BD')
        ->and($visitor->device)->toBe('mobile')
        ->and($visitor->customer_id)->toBeNull()
        ->and(StorefrontPageView::query()->pluck('path')->all())->toBe(['/en/products', '/en/cart']);
    Queue::assertPushed(ResolveVisitorGeo::class, 1);
});

it('links logged in customers and treats invalid tokens as guests', function (): void {
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->store->id, 'name' => 'Customer', 'phone' => '+8801700000000', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $customer = app(CustomerService::class)->enroll($contact, true);
    $token = $customer->createToken('test', ['storefront'], now()->addDay())->plainTextToken;

    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/'], [...$this->headers, 'Authorization' => 'Bearer bogus'])->assertNoContent();
    expect(StorefrontVisitor::query()->sole()->customer_id)->toBeNull();

    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/me'], [...$this->headers, 'Authorization' => 'Bearer '.$token])->assertNoContent();
    expect(StorefrontVisitor::query()->sole()->customer_id)->toBe($customer->id)
        ->and(StorefrontPageView::query()->latest('id')->first()->customer_id)->toBe($customer->id);
});

it('isolates visitors per store and validates input', function (): void {
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/'], $this->headers)->assertNoContent();
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/'], [...$this->headers, 'X-Store-Host' => 'two.localhost'])->assertNoContent();
    expect(StorefrontVisitor::query()->pluck('workspace_id')->sort()->values()->all())->toBe([$this->store->id, $this->other->id]);

    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => 'nope', 'path' => '/'], $this->headers)->assertUnprocessable();
});

it('resolves geo details for public ips and caches them', function (): void {
    Http::fake(['*' => Http::response(['countryCode' => 'US', 'country' => 'United States', 'city' => 'Mountain View'])]);
    $visitor = StorefrontVisitor::query()->create(['workspace_id' => $this->store->id, 'visitor_key' => $this->key, 'ip' => '8.8.8.8', 'first_seen_at' => now(), 'last_seen_at' => now()]);

    (new ResolveVisitorGeo($visitor->id, '8.8.8.8'))->handle();
    (new ResolveVisitorGeo($visitor->id, '8.8.8.8'))->handle();
    (new ResolveVisitorGeo($visitor->id, '192.168.1.1'))->handle();

    expect($visitor->fresh()->only(['country_code', 'country_name', 'city']))->toBe(['country_code' => 'US', 'country_name' => 'United States', 'city' => 'Mountain View']);
    Http::assertSentCount(1);
    expect(Cache::has('commerce:geoip:8.8.8.8'))->toBeTrue();
});

it('shows daily and live visitors to the store owner only for their workspace', function (): void {
    Schema::dropIfExists('users');
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_02_09_055051_create_permission_tables.php'))->up();
    (require app_path('Modules/Settings/Database/Migrations/2026_02_09_120000_create_settings_table.php'))->up();
    (require app_path('Modules/SystemNotifications/Database/Migrations/2026_03_29_000001_create_system_notifications_table.php'))->up();
    Gate::before(fn ($user, $ability) => true);
    $owner = User::factory()->create();
    $this->store->update(['owner_id' => $owner->id]);
    WorkspaceMember::query()->create(['workspace_id' => $this->store->id, 'user_id' => $owner->id, 'role' => 'owner', 'is_active' => true]);
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/en/shirt'], $this->headers)->assertNoContent();
    $foreign = StorefrontVisitor::query()->create(['workspace_id' => $this->other->id, 'visitor_key' => (string) Str::uuid(), 'ip' => '1.1.1.1', 'first_seen_at' => now(), 'last_seen_at' => now()]);

    $this->actingAs($owner);
    $this->get('/dashboard/commerce/visitors')->assertOk()->assertSee('8.8.8.8')->assertSee('/en/shirt')->assertDontSee('1.1.1.1');
    $this->getJson('/dashboard/commerce/visitors/live')->assertOk()->assertJsonPath('count', 1)->assertJsonPath('visitors.0.path', '/en/shirt');
    $this->getJson('/dashboard/commerce/visitors/'.$foreign->id)->assertNotFound();
});
