<?php

use App\Models\Admin;
use App\Models\User;
use App\Modules\AuthApi\Models\SocialAccount;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Settings\Models\Setting;
use App\Modules\Shared\Support\ModuleRegistry;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('renders the WaPro user login page', function (): void {
    $response = $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in to WaPro')
        ->assertSee('Create an account')
        ->assertDontSee('Login as Free Demo')
        ->assertDontSee('Login as Premium Demo')
        ->assertDontSee('value="user@gmail.com"', false)
        ->assertDontSee('value="premium@gmail.com"', false);

    expect($response->getContent())->toMatch('/(resources\/js\/wapro\/auth\.js|assets\/auth-[^"]+\.js)/');
});

it('lets seeded demo users log in straight to the dashboard when onboarding is complete', function (): void {
    $plan = Plan::query()->firstOrCreate(
        ['slug' => 'free'],
        [
            'name' => 'Free',
            'description' => 'Free starter plan for small teams testing WhatsApp automation.',
            'price' => 0,
            'interval' => 'month',
            'limits' => [
                'messages_per_month' => 10000,
                'whatsapp_numbers' => 1,
                'team_members' => 1,
                'automation_ai_builder' => false,
            ],
            'features' => ['10,000 messages per month'],
            'is_active' => true,
            'sort_order' => 10,
        ]
    );

    foreach (['user@gmail.com', 'premium@gmail.com'] as $email) {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => str_contains($email, 'premium') ? 'Premium Demo User' : 'Free Plan User',
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $workspace = Workspace::query()->updateOrCreate(
            ['owner_id' => $user->id],
            [
                'name' => $user->name.' Workspace',
                'slug' => 'demo-'.$user->id,
                'status' => WorkspaceStatus::Active->value,
                'timezone' => config('app.timezone', 'UTC'),
                'settings' => [
                    'category' => 'services',
                    'team_size' => '6-20',
                    'onboarding_completed_at' => now()->toIso8601String(),
                ],
            ]
        );

        Subscription::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active->value,
                'starts_at' => now(),
                'usage' => [],
            ]
        );

        $this->post(route('login'), [
            'email' => $email,
            'password' => 'password',
        ])
            ->assertRedirect(route('user.dashboard'));
    }
});

it('explains password login failure for social login accounts', function (): void {
    $user = User::factory()->create([
        'email' => 'google-user@example.com',
        'email_verified_at' => now(),
    ]);

    SocialAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-user-1',
        'provider_email' => $user->email,
    ]);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => __('This account is connected with :providers. Continue with :providers or reset your password to sign in with email.', [
                'providers' => 'Google',
            ]),
        ]);
});

it('renders the WaPro signup page', function (): void {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your workspace')
        ->assertSee('Free Starter plan')
        ->assertSee('name="password_confirmation"', false);
});

it('redirects authenticated users away from public auth pages to the user dashboard', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('user.dashboard'));

    $this->actingAs($user)
        ->get(route('register'))
        ->assertRedirect(route('user.dashboard'));
});

it('redirects authenticated admins away from admin login to the admin dashboard', function (): void {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.login'))
        ->assertRedirect(route('admin.dashboard'));
});

it('renders the WaPro admin login page', function (): void {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('WaPro Admin')
        ->assertSee('Admin sign in')
        ->assertSee(route('admin.login.submit'), false);
});

it('renders the clean WaPro admin forgot password page', function (): void {
    $this->get(route('admin.password.request'))
        ->assertOk()
        ->assertSee('WaPro Admin')
        ->assertSee('Forgot password')
        ->assertSee('admin@example.com')
        ->assertSee(route('admin.password.email'), false)
        ->assertDontSee('Secure administration portal')
        ->assertDontSee('Audit Log Tracking');
});

it('renders and enforces recaptcha on user and admin login when enabled', function (): void {
    Setting::query()->insert([
        ['key' => 'plugin_turnstile_enabled', 'value' => '1'],
        ['key' => 'plugin_turnstile_site_key', 'value' => 'site-key'],
        ['key' => 'plugin_turnstile_secret_key', 'value' => 'secret-key'],
    ]);
    app('cache')->forget('app_settings');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('class="g-recaptcha"', false)
        ->assertSee('data-sitekey="site-key"', false);

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('class="g-recaptcha"', false)
        ->assertSee('data-sitekey="site-key"', false);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'user@example.com',
            'password' => 'password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['g-recaptcha-response']);

    $this->from(route('admin.login'))
        ->post(route('admin.login.submit'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors(['g-recaptcha-response']);
});

it('redirects admin guests to the admin login page', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

it('renders the admin panel with the WaPro app shell', function (): void {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('class="app-shell"', false)
        ->assertSee('id="appSidebar"', false)
        ->assertSee('class="app-sidebar"', false)
        ->assertSee('class="app-topbar"', false)
        ->assertSee('app-topbar__search', false)
        ->assertSee('Admin', false);
});

it('renders grouped sidebar menu items with submenu toggle markup', function (): void {
    $html = view('components.navigation.sidebar-item', [
        'label' => 'Settings',
        'icon' => 'ph-gear',
        'route' => 'admin.settings.*',
        'children' => [
            ['label' => 'General Settings', 'route' => 'admin.settings.index'],
            ['label' => 'Frontend Themes', 'route' => 'admin.frontend-themes.*'],
        ],
    ])->render();

    expect($html)
        ->toContain('data-action="toggle-submenu"')
        ->toContain('class="submenu')
        ->toContain('General Settings')
        ->toContain('Frontend Themes');
});

it('keeps admin settings under the system settings submenu without duplicate client menu', function (): void {
    $navigation = app(ModuleRegistry::class)->buildNavigation('admin');
    $settingsItem = collect($navigation)->first(fn (array $item): bool => $item['group'] === 'System' && $item['label'] === 'Settings');

    expect($settingsItem)->not->toBeNull();

    expect(collect($settingsItem['children'])->pluck('label')->all())->toContain(
        'General Settings',
        'WhatsApp Cloud',
        'AI Settings',
        'Vector Database',
        'Place API Settings',
        'Payment Gateways',
        'Currencies',
        'Languages',
        'Media Library',
        'Notifications',
    );

    expect(collect($navigation)->contains(fn (array $item): bool => $item['group'] === 'Integrations' && $item['label'] === 'WhatsApp Cloud'))->toBeFalse()
        ->and(collect($navigation)->contains(fn (array $item): bool => $item['group'] === 'Management' && $item['label'] === 'Notifications'))->toBeFalse()
        ->and(collect($settingsItem['children'])->pluck('label')->contains('Home Page Settings'))->toBeFalse()
        ->and(Route::has('admin.home-page-settings.index'))->toBeFalse()
        ->and(collect($navigation)->contains(fn (array $item): bool => $item['label'] === 'Clients'))->toBeFalse();
});
