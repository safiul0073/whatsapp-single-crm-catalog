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
    $this->get('/dashboard/commerce/visitors')->assertOk()->assertSee('/en/shirt')->assertDontSee('1.1.1.1');
    $this->get('/dashboard/commerce/visitors/all')->assertOk()->assertSee('8.8.8.8')->assertDontSee('1.1.1.1');
    $this->get('/dashboard/commerce/visitors/now')->assertOk();
    $this->getJson('/dashboard/commerce/visitors/live')->assertOk()->assertJsonPath('count', 1)->assertJsonPath('visitors.0.path', '/en/shirt');
    $this->getJson('/dashboard/commerce/visitors/'.$foreign->id)->assertNotFound();
});

it('stores edge city headers on the visitor and every page view', function (): void {
    $headers = [...$this->headers, 'X-Client-City' => rawurlencode('Dhaka'), 'X-Client-Region' => 'Dhaka Division'];
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/'], $headers)->assertNoContent();
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/cart'], [...$headers, 'X-Client-City' => '<script>'])->assertNoContent();

    $visitor = StorefrontVisitor::query()->sole();
    expect($visitor->only(['city', 'region']))->toBe(['city' => 'Dhaka', 'region' => 'Dhaka Division'])
        ->and(StorefrontPageView::query()->pluck('city')->all())->toBe(['Dhaka', 'Dhaka'])
        ->and(StorefrontPageView::query()->pluck('device')->unique()->all())->toBe(['mobile']);
});

it('retries geo lookup for visitors without a city and backfills their page views', function (): void {
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/'], $this->headers)->assertNoContent();
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/a'], $this->headers)->assertNoContent();
    Queue::assertPushed(ResolveVisitorGeo::class, 1);

    Cache::forget('commerce:geoip:retry:'.StorefrontVisitor::query()->sole()->id);
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $this->key, 'path' => '/b'], $this->headers)->assertNoContent();
    Queue::assertPushed(ResolveVisitorGeo::class, 2);

    Http::fake(['*' => Http::response(['countryCode' => 'BD', 'country' => 'Bangladesh', 'regionName' => 'Dhaka Division', 'city' => 'Dhaka'])]);
    (new ResolveVisitorGeo(StorefrontVisitor::query()->sole()->id, '8.8.8.8'))->handle();

    expect(StorefrontVisitor::query()->sole()->region)->toBe('Dhaka Division')
        ->and(StorefrontPageView::query()->whereNull('city')->count())->toBe(0);
});

it('does not cache failed geo lookups for a week', function (): void {
    Http::fake(['*' => Http::sequence()->push([], 500)->push(['countryCode' => 'BD', 'country' => 'Bangladesh', 'city' => 'Chattogram'])]);
    $visitor = StorefrontVisitor::query()->create(['workspace_id' => $this->store->id, 'visitor_key' => $this->key, 'ip' => '8.8.8.8', 'first_seen_at' => now(), 'last_seen_at' => now()]);
    (new ResolveVisitorGeo($visitor->id, '8.8.8.8'))->handle();

    expect($visitor->fresh()->city)->toBeNull();
    $this->travel(2)->hours();
    (new ResolveVisitorGeo($visitor->id, '8.8.8.8'))->handle();

    expect($visitor->fresh()->city)->toBe('Chattogram');
});

it('ranks top cities by page views within the selected range for the owner workspace only', function (): void {
    Schema::dropIfExists('users');
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_02_09_055051_create_permission_tables.php'))->up();
    (require app_path('Modules/Settings/Database/Migrations/2026_02_09_120000_create_settings_table.php'))->up();
    (require app_path('Modules/SystemNotifications/Database/Migrations/2026_03_29_000001_create_system_notifications_table.php'))->up();
    Gate::before(fn ($user, $ability) => true);
    $owner = User::factory()->create();
    $this->store->update(['owner_id' => $owner->id]);
    WorkspaceMember::query()->create(['workspace_id' => $this->store->id, 'user_id' => $owner->id, 'role' => 'owner', 'is_active' => true]);

    $track = function (string $city, int $times, ?string $key = null, string $host = 'one.localhost'): void {
        $key ??= (string) Str::uuid();
        foreach (range(1, $times) as $i) {
            $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => $key, 'path' => '/p'.$i, 'referrer' => $i === 1 ? 'https://www.facebook.com/ads' : 'http://one.localhost/p'], [...$this->headers, 'X-Store-Host' => $host, 'X-Client-City' => $city])->assertNoContent();
        }
    };
    $track('Sylhet', 2);
    $track('Dhaka', 3);
    $track('Dhaka', 2);
    $track('Khulna', 9, null, 'two.localhost');
    StorefrontPageView::query()->where('workspace_id', $this->store->id)->where('city', 'Sylhet')->limit(1)->update(['viewed_at' => now()->subDays(20)]);

    $this->actingAs($owner);
    $this->get('/dashboard/commerce/visitors?range=7d')->assertOk()
        ->assertSeeInOrder(['Top cities by page views', 'Dhaka', '5', 'Sylhet', '1'])
        ->assertDontSee('Khulna')
        ->assertSee('Platforms')->assertSee('iOS')
        ->assertSeeInOrder(['Top referrers', 'www.facebook.com'])->assertDontSee('>one.localhost<', false);
    $this->get('/dashboard/commerce/visitors?range=today')->assertOk()->assertSee('Dhaka');
    $this->get('/dashboard/commerce/visitors/all?os=android&range=30d')->assertOk()->assertSee('No visitors match these filters.');
    $this->get('/dashboard/commerce/visitors/all?city=Sylhet&range=30d')->assertOk()->assertSee('Sylhet')->assertDontSee('text-title">Dhaka</span>', false);
    $this->get('/dashboard/commerce/visitors?range=custom&from=2999-01-01')->assertSessionHasErrors('from');
    $this->getJson('/dashboard/commerce/visitors/live')->assertOk()->assertJsonPath('count', 3)->assertJsonPath('cities.0.city', 'Dhaka')->assertJsonPath('cities.0.count', 2);
});

it('classifies platform and browser from the user agent', function (string $agent, string $device, string $os, string $browser): void {
    $this->postJson('/api/storefront/v1/track/page-view', ['visitor_key' => (string) Str::uuid(), 'path' => '/'], [...$this->headers, 'X-Client-User-Agent' => $agent])->assertNoContent();

    expect(StorefrontPageView::query()->latest('id')->first()->only(['device', 'os', 'browser']))->toBe(['device' => $device, 'os' => $os, 'browser' => $browser]);
})->with([
    'iphone safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'mobile', 'ios', 'safari'],
    'android chrome' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36', 'mobile', 'android', 'chrome'],
    'windows edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0', 'desktop', 'windows', 'edge'],
    'mac chrome' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36', 'desktop', 'macos', 'chrome'],
    'facebook in-app' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Mobile/15E148 [FBAN/FBIOS;FBAV/470.0]', 'mobile', 'ios', 'facebook'],
]);
