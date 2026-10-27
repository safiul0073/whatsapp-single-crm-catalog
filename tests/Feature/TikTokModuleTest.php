<?php

use App\Models\Admin;
use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelHubService;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Settings\Models\Setting;
use App\Modules\TikTok\Services\TikTokSettingsService;
use App\Modules\TikTok\Services\TikTokTokenService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function tiktokWorkspace(User $user): Workspace
{
    return Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => 'TikTok Store',
        'slug' => 'tiktok-store-'.$user->id,
        'status' => 'active',
        'timezone' => 'UTC',
        'settings' => [],
    ]);
}

function tiktokConfigured(): void
{
    app(TikTokSettingsService::class)->update(['tiktok_client_key' => 'ck-123', 'tiktok_client_secret' => 'cs-secret', 'tiktok_scopes' => 'user.info.basic,video.upload']);
}

function tiktokAccount(Workspace $workspace, array $credentials = []): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'tiktok',
        'name' => 'Shop TikTok',
        'status' => 'connected',
        'credentials' => $credentials + ['access_token' => 'old-access', 'refresh_token' => 'old-refresh', 'open_id' => 'open-1', 'expires_at' => now()->addMinutes(30)->toIso8601String()],
        'webhook_verify_token' => 'x',
        'provider_account_id' => 'open-1',
    ]);
}

it('registers the TikTok routes, driver and channel hub entry', function (): void {
    expect(Route::has('admin.tiktok.settings.index'))->toBeTrue()
        ->and(Route::has('user.tiktok.callback'))->toBeTrue()
        ->and(app(ChannelManager::class)->driver('tiktok')->getCapabilities())->toBe(['Login'])
        ->and(app(ChannelHubService::class)->setupUrl('tiktok'))->toBe(route('user.tiktok.index'));
});

it('stores the client secret encrypted and keeps it when the field is left blank', function (): void {
    tiktokConfigured();
    $settings = app(TikTokSettingsService::class);

    expect(Setting::query()->where('key', 'tiktok_client_secret')->value('value'))->not->toContain('cs-secret')
        ->and($settings->clientSecret())->toBe('cs-secret')
        ->and($settings->ready())->toBeTrue();

    $settings->update(['tiktok_client_key' => 'ck-456', 'tiktok_client_secret' => '', 'tiktok_scopes' => 'user.info.basic']);

    expect($settings->clientSecret())->toBe('cs-secret')->and($settings->clientKey())->toBe('ck-456');
});

it('sends the user to TikTok with a state and refuses when the app is not configured', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    tiktokWorkspace($user);

    $this->withoutMiddleware()->actingAs($user)->post(route('user.tiktok.connect'))->assertSessionHasErrors('tiktok');

    tiktokConfigured();
    $response = $this->withoutMiddleware()->actingAs($user)->post(route('user.tiktok.connect'));
    $location = $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://www.tiktok.com/v2/auth/authorize/')
        ->and($query)->toMatchArray(['client_key' => 'ck-123', 'response_type' => 'code', 'scope' => 'user.info.basic,video.upload', 'redirect_uri' => route('user.tiktok.callback')])
        ->and(session('tiktok_oauth_state'))->toBe($query['state']);
});

it('connects the account on a valid callback and encrypts the tokens', function (): void {
    tiktokConfigured();
    Http::fake([
        'open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'act-1', 'refresh_token' => 'rft-1', 'open_id' => 'open-9', 'scope' => 'user.info.basic', 'expires_in' => 86400, 'refresh_expires_in' => 31536000]),
        'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'open-9', 'display_name' => 'Fashion Fiest', 'avatar_url' => 'https://example.com/a.jpg']], 'error' => ['code' => 'ok']]),
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = tiktokWorkspace($user);

    $this->withoutMiddleware()->actingAs($user)->withSession(['tiktok_oauth_state' => 'state-abc'])
        ->get(route('user.tiktok.callback', ['code' => 'auth-code', 'state' => 'state-abc']))
        ->assertRedirect(route('user.tiktok.index'))
        ->assertSessionHas('status');

    $account = ChannelAccount::query()->where('provider', 'tiktok')->firstOrFail();

    expect($account->workspace_id)->toBe($workspace->id)
        ->and($account->provider_account_id)->toBe('open-9')
        ->and($account->name)->toBe('Fashion Fiest')
        ->and($account->status->value)->toBe('connected')
        ->and($account->credential('access_token'))->toBe('act-1')
        ->and($account->credential('refresh_token'))->toBe('rft-1')
        ->and($account->getRawOriginal('credentials'))->not->toContain('act-1');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'oauth/token')
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'auth-code'
        && $request['client_secret'] === 'cs-secret');
});

it('rejects a callback with a wrong state or a cancelled sign-in', function (): void {
    tiktokConfigured();
    Http::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);
    tiktokWorkspace($user);

    $this->withoutMiddleware()->actingAs($user)->withSession(['tiktok_oauth_state' => 'right'])
        ->get(route('user.tiktok.callback', ['code' => 'c', 'state' => 'wrong']))
        ->assertRedirect(route('user.tiktok.index'))
        ->assertSessionHas('error');

    $this->withoutMiddleware()->actingAs($user)
        ->get(route('user.tiktok.callback', ['error' => 'access_denied']))
        ->assertSessionHas('error');

    expect(ChannelAccount::query()->where('provider', 'tiktok')->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('refreshes tokens that expire soon and stores the rotated refresh token', function (): void {
    tiktokConfigured();
    Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'open_id' => 'open-1', 'expires_in' => 86400, 'refresh_expires_in' => 31536000])]);
    $workspace = tiktokWorkspace(User::factory()->create());
    $due = tiktokAccount($workspace);
    $fresh = tiktokAccount($workspace, ['expires_at' => now()->addHours(10)->toIso8601String(), 'access_token' => 'still-good']);
    $fresh->update(['provider_account_id' => 'open-2']);

    expect(app(TikTokTokenService::class)->refreshDue())->toBe(1)
        ->and($due->fresh()->credential('access_token'))->toBe('new-access')
        ->and($due->fresh()->credential('refresh_token'))->toBe('new-refresh')
        ->and($fresh->fresh()->credential('access_token'))->toBe('still-good');

    Http::assertSent(fn ($request): bool => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'old-refresh');
});

it('flags the account for reconnecting when TikTok rejects the refresh token but not on a server error', function (): void {
    tiktokConfigured();
    $workspace = tiktokWorkspace(User::factory()->create());
    $tokens = app(TikTokTokenService::class);

    Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::sequence()
        ->push(['error' => 'invalid_grant', 'error_description' => 'Refresh token is invalid.'], 400)
        ->push([], 503)]);
    $rejected = tiktokAccount($workspace, ['expires_at' => now()->subMinute()->toIso8601String()]);

    expect($tokens->accessToken($rejected))->toBeNull()
        ->and($rejected->fresh()->status->value)->toBe('error')
        ->and(data_get($rejected->fresh()->settings, 'last_error'))->toContain('Reconnect TikTok');

    $outage = tiktokAccount($workspace, ['expires_at' => now()->subMinute()->toIso8601String()]);
    $outage->update(['provider_account_id' => 'open-3']);

    expect($tokens->refresh($outage))->toBeFalse()->and($outage->fresh()->status->value)->toBe('connected');
});

it('renders the TikTok page with the connect button, connected accounts and no tokens', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = tiktokWorkspace($user);
    tiktokAccount($workspace);

    $this->withoutMiddleware()->actingAs($user)->get(route('user.tiktok.index'))
        ->assertOk()
        ->assertSee('Admin setup required')
        ->assertSee('Shop TikTok')
        ->assertDontSee('old-access')
        ->assertDontSee('old-refresh');

    tiktokConfigured();

    $this->withoutMiddleware()->actingAs($user)->get(route('user.tiktok.index'))
        ->assertOk()
        ->assertSee('Connect another account')
        ->assertDontSee('Admin setup required');
});

it('lets the admin switch TikTok Login and Shop on or off and keeps both secrets encrypted', function (): void {
    $settings = app(TikTokSettingsService::class);
    $settings->update(['tiktok_login_enabled' => true, 'tiktok_client_key' => 'ck', 'tiktok_client_secret' => 'cs', 'tiktok_shop_enabled' => true, 'tiktok_shop_app_key' => 'shop-key', 'tiktok_shop_app_secret' => 'shop-secret']);

    expect($settings->ready())->toBeTrue()
        ->and($settings->shopReady())->toBeTrue()
        ->and($settings->shopAppSecret())->toBe('shop-secret')
        ->and(Setting::query()->where('key', 'tiktok_shop_app_secret')->value('value'))->not->toContain('shop-secret');

    $settings->update(['tiktok_login_enabled' => false, 'tiktok_shop_enabled' => false, 'tiktok_shop_app_secret' => '']);

    expect($settings->ready())->toBeFalse()
        ->and($settings->shopReady())->toBeFalse()
        ->and($settings->shopAppSecret())->toBe('shop-secret');
});

it('tells owners when TikTok is off, and when Shop is enabled but not connectable yet', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    tiktokWorkspace($user);
    $settings = app(TikTokSettingsService::class);

    $settings->update(['tiktok_login_enabled' => false]);
    $this->withoutMiddleware()->actingAs($user)->get(route('user.tiktok.index'))
        ->assertOk()->assertSee('turned off by the platform administrator')->assertSee('TikTok Shop is not available yet');

    $settings->update(['tiktok_login_enabled' => true, 'tiktok_client_key' => 'ck', 'tiktok_client_secret' => 'cs', 'tiktok_shop_enabled' => true, 'tiktok_shop_app_key' => 'k', 'tiktok_shop_app_secret' => 's']);
    $this->withoutMiddleware()->actingAs($user)->get(route('user.tiktok.index'))
        ->assertOk()->assertSee('Connect TikTok')->assertSee('Connecting a shop will be available soon');
});

it('lets an admin open and save the TikTok settings page without exposing saved secrets', function (): void {
    foreach (['tiktok.settings.view', 'tiktok.settings.edit'] as $permission) {
        Permission::findOrCreate($permission, 'admin');
    }
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['tiktok.settings.view', 'tiktok.settings.edit']);

    $this->actingAs($admin, 'admin')->get(route('admin.tiktok.settings.index'))->assertOk()->assertSee('TikTok Login')->assertSee('TikTok Shop');

    $this->actingAs($admin, 'admin')->put(route('admin.tiktok.settings.update'), [
        'tiktok_login_enabled' => '1',
        'tiktok_client_key' => 'ck-1',
        'tiktok_client_secret' => 'cs-top-secret',
        'tiktok_scopes' => 'user.info.basic',
        'tiktok_shop_enabled' => '1',
        'tiktok_shop_app_key' => 'shop-key',
        'tiktok_shop_app_secret' => 'shop-top-secret',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($admin, 'admin')->get(route('admin.tiktok.settings.index'))
        ->assertOk()
        ->assertSee('ck-1')
        ->assertDontSee('cs-top-secret')
        ->assertDontSee('shop-top-secret');

    expect(app(TikTokSettingsService::class)->shopReady())->toBeTrue();

    $this->actingAs($admin, 'admin')->put(route('admin.tiktok.settings.update'), ['tiktok_login_enabled' => '1', 'tiktok_client_key' => '', 'tiktok_scopes' => 'user.info.basic'])
        ->assertSessionHasErrors('tiktok_client_key');
});
