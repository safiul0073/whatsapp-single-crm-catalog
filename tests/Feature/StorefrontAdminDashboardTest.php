<?php

use App\Models\User;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Commerce\Storefront\Models\StorefrontMenu;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Models\StorefrontSlider;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Tests\Support\StorefrontDatabase;

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'storefront.platform_secret' => str_repeat('p', 40),
        'storefront.preview_secret' => str_repeat('v', 40),
        'cache.default' => 'array',
    ]);
    DB::purge('sqlite');
    StorefrontDatabase::migrate();
    Schema::dropIfExists('users');
    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
    (require database_path('migrations/2026_02_09_055051_create_permission_tables.php'))->up();
    (require app_path('Modules/SystemNotifications/Database/Migrations/2026_03_29_000001_create_system_notifications_table.php'))->up();
    Queue::fake();

    Gate::before(fn ($user, $ability) => true);
});

it('renders the improved storefront management dashboard with live preview and settings tabs', function (): void {
    $user = User::query()->create([
        'id' => 1,
        'name' => 'Store Owner',
        'email' => 'owner@example.com',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);

    $workspace = Workspace::query()->create([
        'name' => 'Demo Workspace',
        'slug' => 'demo-workspace-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    StoreDomain::query()->create([
        'workspace_id' => $workspace->id,
        'host' => 'demo.localhost',
        'handle' => 'demo',
        'type' => 'system',
        'status' => 'active',
        'is_primary' => true,
        'verification_token' => 'test-token',
    ]);

    $response = $this->actingAs($user)->get('/dashboard/commerce/storefront');

    $response->assertOk();
    $response->assertSee('Storefront Studio &amp; Settings', false);
    $response->assertSee('Storefront Theme &amp; Identity', false);
    $response->assertSee('Live Interactive Preview', false);
    $response->assertSee('Curated Theme Presets', false);
    $response->assertSee('Domains &amp; DNS', false);
    $response->assertSee('Navigation Menus', false);
    $response->assertSee('Hero Sliders', false);
    $response->assertSee('Pages &amp; CMS', false);
    $response->assertSee('Coupons &amp; Promo', false);
    $response->assertSee('Review Moderation', false);
    $response->assertSee('Inquiries &amp; Content', false);
});

it('smoothly saves updated storefront settings including colors and relative logo paths', function (): void {
    $user = User::query()->create([
        'id' => 1,
        'name' => 'Store Owner',
        'email' => 'owner@example.com',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);

    $workspace = Workspace::query()->create([
        'name' => 'Demo Workspace',
        'slug' => 'demo-workspace-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->put('/dashboard/commerce/storefront/settings', [
        'config' => [
            'name' => 'Apex Apparel Studio',
            'primary' => '#1FAA53',
            'hover' => '#178A43',
            'footer_color' => '#075E54',
            'logo' => 'settings/brand-logo.png',
            'announcement' => 'Free global shipping on orders over $75! ✨',
            'contact_email' => 'support@apexapparel.com',
            'contact_phone' => '+18005550199',
            'footer_text' => 'Premium urban fashion handcrafted for everyday lifestyle.',
            'password_login' => 1,
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Settings saved.');

    $saved = StorefrontSetting::query()->where('workspace_id', $workspace->id)->first();
    expect($saved)->not->toBeNull();
    expect($saved->config['name'])->toBe('Apex Apparel Studio')
        ->and($saved->config['primary'])->toBe('#1FAA53')
        ->and($saved->config['hover'])->toBe('#178A43')
        ->and($saved->config['footer_color'])->toBe('#075E54')
        ->and($saved->config['logo'])->toBe('settings/brand-logo.png')
        ->and($saved->config['announcement'])->toBe('Free global shipping on orders over $75! ✨')
        ->and($saved->config['contact_email'])->toBe('support@apexapparel.com')
        ->and((bool) $saved->config['password_login'])->toBeTrue();
});

it('renders media library integration for logo and hero slider slides without icon overlaps', function (): void {
    $user = User::query()->create([
        'id' => 2,
        'name' => 'Store Owner 2',
        'email' => 'owner2@example.com',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);

    $workspace = Workspace::query()->create([
        'name' => 'Demo Workspace 2',
        'slug' => 'demo-workspace-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get('/dashboard/commerce/storefront');

    $response->assertOk();
    $response->assertSee('openGalleryForLogo()', false);
    $response->assertSee('openGalleryForSlide', false);
    $response->assertSee('Choose from Image Gallery', false);
    $response->assertDontSee('form-input pl-10', false);
});

it('creates and saves hero sliders with slides containing image paths', function (): void {
    $user = User::query()->create([
        'id' => 3,
        'name' => 'Store Owner 3',
        'email' => 'owner3@example.com',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);

    $workspace = Workspace::query()->create([
        'name' => 'Demo Workspace 3',
        'slug' => 'demo-workspace-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->post('/dashboard/commerce/storefront/sliders', [
        'name' => 'Main Hero Slider',
        'slides' => json_encode([
            [
                'title' => 'Summer Flash Sale',
                'subtitle' => 'Up to 50% off seasonal items',
                'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8',
                'mobile_image' => 'sliders/mobile-summer.jpg',
                'video' => 'sliders/hero-runway.mp4',
                'button_text' => 'Shop Now',
                'button_link' => '/products',
                'active' => true,
            ],
        ]),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Slider saved.');

    $slider = StorefrontSlider::query()->where('workspace_id', $workspace->id)->first();
    expect($slider)->not->toBeNull();
    expect($slider->name)->toBe('Main Hero Slider');
    expect($slider->slides)->toHaveCount(1);
    expect($slider->slides[0]['title'])->toBe('Summer Flash Sale');
    expect($slider->slides[0]['image'])->toBe('https://images.unsplash.com/photo-1441986300917-64674bd600d8');
    expect($slider->slides[0]['mobile_image'])->toBe('sliders/mobile-summer.jpg');
    expect($slider->slides[0]['video'])->toBe('sliders/hero-runway.mp4');

    $dashboardResponse = $this->actingAs($user)->get('/dashboard/commerce/storefront');
    $dashboardResponse->assertOk();
    $dashboardResponse->assertSee('String(activeSliderId) === \''.$slider->id.'\'', false);
    $dashboardResponse->assertSee('Background Video URL (Optional — MP4, WebM, or YouTube)', false);
});

it('auto-provisions, rotates, and authenticates storefront env credentials from the dashboard', function (): void {
    config([
        'storefront.platform_secret' => '',
        'storefront.revalidation_secret' => '',
        'storefront.preview_secret' => '',
    ]);

    $user = User::query()->create([
        'id' => 4,
        'name' => 'Store Owner 4',
        'email' => 'owner4@example.com',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);

    $workspace = Workspace::query()->create([
        'name' => 'Demo Workspace 4',
        'slug' => 'demo-workspace-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    StoreDomain::query()->create([
        'workspace_id' => $workspace->id,
        'host' => 'owner4.localhost',
        'handle' => 'owner4',
        'type' => 'system',
        'status' => 'active',
        'is_primary' => true,
        'verification_token' => 'verify-4',
    ]);

    $this->actingAs($user)
        ->get('/dashboard/commerce/storefront')
        ->assertOk()
        ->assertSee('API &amp; Deployment (.env)', false)
        ->assertSee('STOREFRONT_PLATFORM_SECRET', false)
        ->assertSee('STOREFRONT_SESSION_SECRET', false)
        ->assertSee('STOREFRONT_REVALIDATION_SECRET', false)
        ->assertSee('STOREFRONT_PREVIEW_SECRET', false);

    $initialSetting = StorefrontSetting::query()->where('workspace_id', $workspace->id)->first();
    $initialPlatformSecret = $initialSetting?->config['deployment']['platform_secret'] ?? '';
    expect(strlen($initialPlatformSecret))->toBe(64);

    // Rotate all secrets
    $this->actingAs($user)
        ->post(route('user.commerce.storefront.credentials.generate'), ['target' => 'all'])
        ->assertRedirect();

    $rotatedSetting = StorefrontSetting::query()->where('workspace_id', $workspace->id)->first();
    $newPlatformSecret = $rotatedSetting?->config['deployment']['platform_secret'] ?? '';
    expect(strlen($newPlatformSecret))->toBe(64)
        ->and($newPlatformSecret)->not->toBe($initialPlatformSecret);

    // Update deployment endpoints
    $this->actingAs($user)
        ->put(route('user.commerce.storefront.credentials.update'), [
            'marketing_api_url' => 'https://api.myshop.com',
            'media_origin' => 'https://cdn.myshop.com',
            'storefront_url' => 'https://shop.myshop.com',
            'root_domain' => 'myshop.com',
            'dns_target' => 'cname.myshop.com',
        ])
        ->assertRedirect();

    $updatedDeployment = StorefrontSetting::query()->where('workspace_id', $workspace->id)->first()?->config['deployment'] ?? [];
    expect($updatedDeployment['marketing_api_url'])->toBe('https://api.myshop.com')
        ->and($updatedDeployment['storefront_url'])->toBe('https://shop.myshop.com');

    // Authenticate against internal storefront API using the dashboard-generated platform_secret
    $this->withHeaders(['X-Storefront-Secret' => $newPlatformSecret])
        ->getJson('/internal/stores/resolve?host=owner4.localhost')
        ->assertOk()
        ->assertJsonPath('data.store_id', $workspace->id);
});

it('allows creating, updating, toggling status, and deleting coupons from the storefront dashboard', function (): void {
    $user = User::query()->create([
        'id' => 1,
        'name' => 'Store Owner',
        'email' => 'owner@example.com',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);

    $workspace = Workspace::query()->create([
        'name' => 'Demo Workspace',
        'slug' => 'demo-workspace-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    // 1. Create a coupon
    $this->actingAs($user)
        ->post(route('user.commerce.storefront.coupons.store'), [
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20,
            'min_order' => 50,
            'max_discount' => 100,
            'usage_limit' => 200,
            'per_customer_limit' => 1,
            'active' => 1,
        ])
        ->assertRedirect();

    $coupon = Coupon::query()->where('workspace_id', $workspace->id)->where('code', 'SAVE20')->first();
    expect($coupon)->not->toBeNull()
        ->and($coupon->active)->toBeTrue()
        ->and((float) $coupon->value)->toBe(20.0);

    // 2. Toggle active to inactive
    $toggleResponse = $this->actingAs($user)
        ->post(route('user.commerce.storefront.coupons.toggle', $coupon->id));
    $toggleResponse->assertRedirect(route('user.commerce.storefront.index').'?tab=coupons#coupons');
    $toggleResponse->assertSessionHas('storefront_tab', 'coupons');

    expect($coupon->fresh()->active)->toBeFalse();

    // 3. Toggle back to active
    $this->actingAs($user)
        ->post(route('user.commerce.storefront.coupons.toggle', $coupon->id))
        ->assertRedirect(route('user.commerce.storefront.index').'?tab=coupons#coupons');

    expect($coupon->fresh()->active)->toBeTrue();

    // 4. Update coupon
    $this->actingAs($user)
        ->put(route('user.commerce.storefront.coupons.update', $coupon->id), [
            'code' => 'SAVE25',
            'type' => 'percentage',
            'value' => 25,
            'min_order' => 60,
            'max_discount' => 150,
            'usage_limit' => 300,
            'per_customer_limit' => 2,
            'active' => 1,
        ])
        ->assertRedirect();

    $updated = $coupon->fresh();
    expect($updated->code)->toBe('SAVE25')
        ->and((float) $updated->value)->toBe(25.0)
        ->and((float) $updated->min_order)->toBe(60.0);

    // 5. Delete coupon
    $this->actingAs($user)
        ->delete(route('user.commerce.storefront.coupons.delete', $coupon->id))
        ->assertRedirect();

    expect(Coupon::query()->find($coupon->id))->toBeNull();
});

it('saves navigation menus with mega-menu styles, badges, and nested sub-links', function (): void {
    $user = User::query()->create([
        'id' => 6,
        'name' => 'Menu Owner',
        'email' => 'menu-owner@example.com',
        'password' => bcrypt('secret123'),
        'email_verified_at' => now(),
    ]);

    $workspace = Workspace::query()->create([
        'name' => 'Menu Workspace',
        'slug' => 'menu-workspace-'.Str::uuid(),
        'owner_id' => $user->id,
        'status' => 'active',
    ]);

    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->put(route('user.commerce.storefront.menus'), [
        'slot' => 'header',
        'items' => [
            [
                'label' => 'Summer Edit',
                'href' => '/collections/summer',
                'mega' => 'custom',
                'badge' => 'HOT',
                'children' => [
                    ['label' => 'Linen Shirts', 'href' => '/category/men-tops'],
                    ['label' => 'Resort Shorts', 'href' => '/category/men-bottoms'],
                ],
            ],
            [
                'label' => 'Order Tracking',
                'href' => '/track-order',
                'mega' => 'none',
            ],
        ],
    ]);

    $response->assertRedirect(route('user.commerce.storefront.index').'?tab=menus&slot=header#menus');
    $response->assertSessionHas('success', 'Menu saved.');

    $menu = StorefrontMenu::query()
        ->where('workspace_id', $workspace->id)
        ->where('slot', 'header')
        ->first();

    expect($menu)->not->toBeNull()
        ->and($menu->items)->toHaveCount(2)
        ->and($menu->items[0]['label'])->toBe('Summer Edit')
        ->and($menu->items[0]['mega'])->toBe('custom')
        ->and($menu->items[0]['badge'])->toBe('HOT')
        ->and($menu->items[0]['children'])->toHaveCount(2);
});
