<?php

use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Models\MetaBusinessConnection;
use App\Modules\MetaSocial\Services\MetaAppSettingsService;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use App\Modules\Workspaces\Services\WorkspacePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(MetaAppSettingsService::class)->update([
        'meta_app_id' => 'meta-app',
        'meta_app_secret' => 'meta-secret',
        'meta_business_config_id' => 'business-config',
    ]);
    app(WhatsAppSettingsService::class)->update([
        'whatsapp_auto_sync_templates' => false,
        'whatsapp_auto_sync_phone_numbers' => false,
    ]);

    Http::fake([
        '*/oauth/access_token*' => Http::response(['access_token' => 'EAAbusiness-token']),
        '*/debug_token*' => Http::response(['data' => [
            'scopes' => ['business_management', 'whatsapp_business_management', 'pages_show_list', 'pages_messaging', 'instagram_basic', 'ads_read', 'catalog_management'],
            'granular_scopes' => [
                ['scope' => 'business_management', 'target_ids' => ['biz-1']],
                ['scope' => 'whatsapp_business_management', 'target_ids' => ['waba-1']],
                ['scope' => 'catalog_management', 'target_ids' => ['cat-1']],
            ],
        ]]),
        '*/me/businesses*' => Http::response(['data' => [['id' => 'biz-1', 'name' => 'Acme Fashion']]]),
        '*/subscribed_apps' => Http::response(['success' => true]),
        '*/waba-1/phone_numbers*' => Http::response(['data' => [['id' => 'phone-1', 'display_phone_number' => '+880 1700-000001', 'verified_name' => 'Acme']]]),
        '*/waba-1*' => Http::response(['id' => 'waba-1', 'name' => 'Acme WABA']),
        '*/me/accounts*' => Http::response(['data' => [
            ['id' => 'page-1', 'name' => 'Acme Page', 'access_token' => 'page-1-token', 'instagram_business_account' => ['id' => 'ig-1', 'username' => 'acme.shop']],
            ['id' => 'page-2', 'name' => 'Outlet Page', 'access_token' => 'page-2-token'],
        ]]),
        '*/me/adaccounts*' => Http::response(['data' => [['id' => 'act_1', 'account_id' => '1', 'name' => 'Acme Ads', 'currency' => 'BDT']]]),
        '*/cat-1*' => Http::response(['id' => 'cat-1', 'name' => 'Acme Catalog']),
        '*/page-1*' => Http::response(['id' => 'page-1', 'name' => 'Acme Page']),
        '*/page-2*' => Http::response(['id' => 'page-2', 'name' => 'Outlet Page']),
        '*/ig-1*' => Http::response(['id' => 'ig-1', 'username' => 'acme.shop']),
    ]);
});

function metaBusinessUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

function connectMetaBusiness(User $user): void
{
    test()->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.business.connect'), ['code' => 'login-code'])
        ->assertRedirect(route('user.meta-social.business.show'));
}

it('shows the Connect your Meta Business card on the channel hub', function (): void {
    Gate::before(fn (): bool => true);

    $this->withoutMiddleware()
        ->actingAs(metaBusinessUser())
        ->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('Connect with Meta')
        ->assertSee('data-meta-connect="business"', false)
        ->assertSee('data-config-id="business-config"', false);
});

it('discovers every granted Meta asset from one login', function (): void {
    $user = metaBusinessUser();
    connectMetaBusiness($user);

    $connection = MetaBusinessConnection::query()->sole();

    expect($connection->business_name)->toBe('Acme Fashion')
        ->and($connection->accessToken())->toBe('EAAbusiness-token')
        ->and($connection->assetsOfType('whatsapp')[0]['id'])->toBe('waba-1|phone-1')
        ->and(collect($connection->assetsOfType('messenger'))->pluck('id')->all())->toBe(['page-1', 'page-2'])
        ->and($connection->assetsOfType('instagram')[0]['username'])->toBe('acme.shop')
        ->and($connection->assetsOfType('ad_accounts')[0]['id'])->toBe('act_1')
        ->and($connection->assetsOfType('catalogs')[0]['name'])->toBe('Acme Catalog');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-social.business.show'))
        ->assertOk()
        ->assertSee('Acme Fashion')
        ->assertSee('Outlet Page')
        ->assertSee('@acme.shop')
        ->assertSee('Acme Ads')
        ->assertDontSee('EAAbusiness-token')
        ->assertDontSee('page-1-token');
});

it('turns the selected assets into channels through the existing setup services', function (): void {
    Gate::before(fn (): bool => true);
    $user = metaBusinessUser();
    connectMetaBusiness($user);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.business.activate'), [
            'whatsapp' => 'waba-1|phone-1',
            'messenger' => ['page-2', 'not-granted'],
            'instagram' => ['ig-1'],
            'ad_accounts' => ['act_1'],
            'catalogs' => ['cat-1'],
        ])
        ->assertRedirect(route('user.meta-social.business.result'));

    $whatsapp = ChannelAccount::query()->where('provider', 'whatsapp')->sole();
    $messenger = ChannelAccount::query()->where('provider', 'messenger')->sole();
    $instagram = ChannelAccount::query()->where('provider', 'instagram')->sole();

    expect($whatsapp->provider_account_id)->toBe('waba-1')
        ->and($whatsapp->provider_phone_id)->toBe('phone-1')
        ->and($whatsapp->credential('source'))->toBe('meta_business')
        ->and($messenger->provider_account_id)->toBe('page-2')
        ->and($messenger->credential('access_token'))->toBe('page-2-token')
        ->and($instagram->provider_phone_id)->toBe('page-1')
        ->and(MetaBusinessConnection::query()->sole()->selected)->toMatchArray([
            'messenger' => ['page-2'],
            'ad_accounts' => ['act_1'],
            'catalogs' => ['cat-1'],
        ]);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/waba-1/subscribed_apps'));
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/page-2/subscribed_apps'));
});

it('skips WhatsApp for members without channel management access', function (): void {
    $this->mock(WorkspacePermissionResolver::class)
        ->shouldReceive('can')
        ->andReturnUsing(fn (User $user, string $ability): bool => $ability !== 'channels.manage');
    $user = metaBusinessUser();
    connectMetaBusiness($user);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.business.activate'), [
            'whatsapp' => 'waba-1|phone-1',
            'messenger' => ['page-1'],
        ])
        ->assertRedirect(route('user.meta-social.business.result'));

    expect(ChannelAccount::query()->where('provider', 'whatsapp')->exists())->toBeFalse()
        ->and(ChannelAccount::query()->where('provider', 'messenger')->count())->toBe(1);
});

it('removes the business login but keeps connected channels', function (): void {
    $user = metaBusinessUser();
    connectMetaBusiness($user);

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.business.activate'), ['messenger' => ['page-1']]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->delete(route('user.meta-social.business.disconnect'))
        ->assertRedirect(route('user.channels.index'));

    expect(MetaBusinessConnection::query()->exists())->toBeFalse()
        ->and(ChannelAccount::query()->where('provider', 'messenger')->sole()->status->value)->toBe('connected');
});

it('refuses to connect when the business configuration is missing', function (): void {
    app(MetaAppSettingsService::class)->update(['meta_business_config_id' => '']);

    $this->withoutMiddleware()
        ->actingAs(metaBusinessUser())
        ->post(route('user.meta-social.business.connect'), ['code' => 'login-code'])
        ->assertSessionHasErrors('meta_business');

    expect(MetaBusinessConnection::query()->exists())->toBeFalse();
});

it('shows what each newly connected channel still needs after activation', function (): void {
    Gate::before(fn (): bool => true);
    $user = metaBusinessUser();
    connectMetaBusiness($user);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.business.activate'), ['messenger' => ['page-2']])
        ->assertRedirect(route('user.meta-social.business.result'));

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-social.business.result'))
        ->assertOk()
        ->assertSee('Here is where each channel stands')
        ->assertSee('Outlet Page')
        ->assertSee('data-activation-channel="messenger"', false)
        ->assertSee('data-readiness-checklist', false)
        ->assertSee('Messaging permissions granted')
        ->assertDontSee('page-2-token');

    expect(data_get(ChannelAccount::query()->where('provider', 'messenger')->sole()->settings, 'readiness.items'))->not->toBeEmpty();
});

it('warns in the picker about Pages the user cannot message from', function (): void {
    $user = metaBusinessUser();

    MetaBusinessConnection::query()->create([
        'workspace_id' => app(WorkspaceResolver::class)->current($user)->id,
        'business_name' => 'Acme Fashion',
        'credentials' => ['access_token' => 'EAAbusiness-token'],
        'assets' => [
            'messenger' => [['id' => 'page-9', 'name' => 'Read Only Page', 'can_message' => '0']],
            'whatsapp' => [['id' => 'waba-1|phone-1', 'waba_id' => 'waba-1', 'waba_name' => 'Acme WABA', 'phone_number_id' => 'phone-1', 'display_phone_number' => '+880 1700-000001', 'verified_name' => 'Acme', 'name_status' => 'PENDING_REVIEW', 'status' => 'CONNECTED']],
        ],
        'connected_at' => now(),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-social.business.show'))
        ->assertOk()
        ->assertSee('You do not have Messages access on this Page')
        ->assertSee('Display name is waiting for Meta review');
});
