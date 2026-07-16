<?php

use App\Models\User;
use App\Modules\AuthApi\Models\SocialAccount;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Providers\SettingsServiceProvider;
use App\Modules\Settings\Services\SettingsService;
use App\Rules\RecaptchaValid;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('registers softivus storage plugin and social settings groups', function (): void {
    $settings = config('settings');

    expect($settings)->toHaveKeys(['storage', 'plugins', 'social'])
        ->and($settings['storage']['settings']['storage_provider']['type'])->toBe('tile_select')
        ->and($settings['plugins']['settings'])->toHaveKeys([
            'plugin_ga4_enabled',
            'plugin_tawk_enabled',
            'plugin_turnstile_enabled',
        ])
        ->and($settings['social']['settings'])->toHaveKeys([
            'social_google_enabled',
            'social_google_callback_url',
            'social_facebook_enabled',
            'social_facebook_callback_url',
            'social_github_enabled',
            'social_github_callback_url',
        ])
        ->and(Route::has('social.redirect'))->toBeTrue()
        ->and(Route::has('social.callback'))->toBeTrue()
        ->and(Schema::hasTable('social_accounts'))->toBeTrue();
});

it('renders copyable social callback urls without persisting them as settings', function (): void {
    URL::forceRootUrl('https://wapro.test');
    URL::forceScheme('https');

    $groups = app(SettingsService::class)->getGroupedDefinitions();

    expect($groups['social']['settings']['social_google_callback_url']['value'])->toBe('https://wapro.test/auth/google/callback')
        ->and($groups['social']['settings']['social_facebook_callback_url']['value'])->toBe('https://wapro.test/auth/facebook/callback')
        ->and($groups['social']['settings']['social_github_callback_url']['value'])->toBe('https://wapro.test/auth/github/callback');

    $this->withoutMiddleware()
        ->put(route('admin.settings.update'), [
            '_active_tab' => 'social',
            'settings' => array_merge(defaultSettingsPayload(), [
                'social_google_enabled' => '1',
                'social_google_callback_url' => 'https://evil.test/callback',
            ]),
        ])
        ->assertRedirect(route('admin.settings.index').'#social');

    expect(Setting::query()->where('key', 'social_google_callback_url')->exists())->toBeFalse()
        ->and(setting('social_google_enabled'))->toBeTrue();
});

it('applies s3 and r2 storage settings to the public disk', function (): void {
    Setting::query()->insert([
        ['key' => 'storage_provider', 'value' => 'r2'],
        ['key' => 'storage_s3_key', 'value' => 'r2-key'],
        ['key' => 'storage_s3_secret', 'value' => 'r2-secret'],
        ['key' => 'storage_s3_bucket', 'value' => 'media'],
        ['key' => 'storage_s3_endpoint', 'value' => 'https://account.r2.cloudflarestorage.com'],
        ['key' => 'storage_s3_url', 'value' => 'https://media.example.com'],
    ]);
    app('cache')->forget('app_settings');

    callSettingsProviderMethod('applyStorageSettings');

    expect(config('filesystems.disks.public.driver'))->toBe('s3')
        ->and(config('filesystems.disks.public.key'))->toBe('r2-key')
        ->and(config('filesystems.disks.public.region'))->toBe('auto')
        ->and(config('filesystems.disks.public.use_path_style_endpoint'))->toBeTrue();
});

it('renders public plugin scripts only when plugins are enabled and configured', function (): void {
    expect(Blade::render('<x-plugins.head-scripts />'))->not->toContain('googletagmanager.com', 'embed.tawk.to');

    Setting::query()->insert([
        ['key' => 'plugin_ga4_enabled', 'value' => '1'],
        ['key' => 'plugin_ga4_measurement_id', 'value' => 'G-ABC123'],
        ['key' => 'plugin_tawk_enabled', 'value' => '1'],
        ['key' => 'plugin_tawk_property_id', 'value' => 'property-id'],
        ['key' => 'plugin_tawk_widget_id', 'value' => 'widget-id'],
    ]);

    app('cache')->forget('app_settings');

    expect(Blade::render('<x-plugins.head-scripts />'))
        ->toContain('googletagmanager.com/gtag/js?id=G-ABC123')
        ->toContain('embed.tawk.to');

    $frontendHtml = view('frontend.shared.layouts.page', [
        'page' => (object) [
            'title' => 'Frontend Page',
            'meta_title' => null,
            'meta_description' => null,
        ],
        'resolvedSections' => [],
        'themeKey' => 'classic',
        'themeVars' => [],
    ])->render();

    expect($frontendHtml)
        ->toContain('googletagmanager.com/gtag/js?id=G-ABC123')
        ->toContain('embed.tawk.to')
        ->toContain('property-id')
        ->toContain('widget-id');

    Setting::query()->where('key', 'plugin_tawk_property_id')->update([
        'value' => 'https://embed.tawk.to/full-property/full-widget',
    ]);
    Setting::query()->where('key', 'plugin_tawk_widget_id')->update([
        'value' => '',
    ]);
    app('cache')->forget('app_settings');

    expect(Blade::render('<x-plugins.head-scripts />'))
        ->toContain('full-property')
        ->toContain('full-widget');
});

it('validates recaptcha only when enabled and configured', function (): void {
    $disabledValidator = Validator::make([], [
        'g-recaptcha-response' => [new RecaptchaValid],
    ]);

    expect($disabledValidator->passes())->toBeTrue();

    Setting::query()->insert([
        ['key' => 'plugin_turnstile_enabled', 'value' => '1'],
        ['key' => 'plugin_turnstile_secret_key', 'value' => 'secret'],
    ]);
    app('cache')->forget('app_settings');

    $missingValidator = Validator::make([], [
        'g-recaptcha-response' => [new RecaptchaValid],
    ]);

    expect($missingValidator->passes())->toBeFalse();

    Http::fake([
        'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
    ]);

    $validValidator = Validator::make(['g-recaptcha-response' => 'token'], [
        'g-recaptcha-response' => [new RecaptchaValid],
    ]);

    expect($validValidator->passes())->toBeTrue();
});

it('logs in users through enabled web social providers', function (): void {
    Notification::fake();

    Role::findOrCreate('user', 'web');
    Setting::query()->insert([
        ['key' => 'social_google_enabled', 'value' => '1'],
        ['key' => 'social_google_client_id', 'value' => 'client-id'],
        ['key' => 'social_google_client_secret', 'value' => 'client-secret'],
    ]);
    app('cache')->forget('app_settings');

    Config::set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'redirect' => route('social.callback', 'google'),
    ]);

    Socialite::shouldReceive('driver->user')
        ->once()
        ->andReturn(new class implements SocialiteUserContract
        {
            public string $token = 'access-token';

            public ?string $refreshToken = null;

            public int $expiresIn = 3600;

            public function getId()
            {
                return 'google-user-1';
            }

            public function getNickname()
            {
                return null;
            }

            public function getName()
            {
                return 'Google User';
            }

            public function getEmail()
            {
                return 'google-user@example.com';
            }

            public function getAvatar()
            {
                return 'https://example.com/avatar.png';
            }
        });

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('user.dashboard'));

    $user = User::query()->where('email', 'google-user@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->where('provider_user_id', 'google-user-1')
            ->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Notification::assertNotSentTo($user, VerifyEmail::class);
});

function callSettingsProviderMethod(string $method): void
{
    $provider = new SettingsServiceProvider(app());
    $reflection = new ReflectionMethod($provider, $method);
    $reflection->setAccessible(true);
    $reflection->invoke($provider);
}

function defaultSettingsPayload(): array
{
    $payload = [];

    foreach (config('settings', []) as $group) {
        foreach ($group['settings'] as $key => $definition) {
            if (! empty($definition['readonly']) || ($definition['type'] ?? null) === 'readonly_url') {
                continue;
            }

            $payload[$key] = match ($definition['type'] ?? 'text') {
                'boolean', 'feature' => ($definition['default'] ?? false) ? '1' : '0',
                default => (string) ($definition['default'] ?? ''),
            };
        }
    }

    return $payload;
}
