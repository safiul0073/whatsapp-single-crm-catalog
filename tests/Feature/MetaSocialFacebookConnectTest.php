<?php

use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(MetaSocialSettingsService::class)->update([
        'meta_social_embedded_signup_enabled' => true,
        'meta_social_app_id' => 'meta-app-id',
        'meta_social_app_secret' => 'meta-secret',
        'meta_social_messenger_config_id' => 'messenger-config',
        'meta_social_instagram_config_id' => 'instagram-config',
    ]);
});

function fakeFacebookLogin(array $pages, bool $subscribeOk = true): void
{
    Http::fake([
        '*/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        '*/me/accounts*' => Http::response(['data' => $pages]),
        '*/subscribed_apps' => $subscribeOk
            ? Http::response(['success' => true])
            : Http::response(['error' => ['message' => 'Requires pages_manage_metadata']], 403),
        '*/page-1*' => Http::response(['id' => 'page-1', 'name' => 'Main Page']),
        '*/page-2*' => Http::response(['id' => 'page-2', 'name' => 'Second Page']),
        '*/ig-1*' => Http::response(['id' => 'ig-1', 'username' => 'shop.one']),
    ]);
}

function facebookConnectUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

it('shows the Connect with Facebook buttons on the channel hub when only product configs exist', function (): void {
    $this->withoutMiddleware()
        ->actingAs(facebookConnectUser())
        ->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('Connect with Facebook')
        ->assertSee('data-meta-connect="messenger"', false)
        ->assertSee('data-config-id="instagram-config"', false);
});

it('connects the only granted Page straight away and subscribes it to webhooks', function (): void {
    fakeFacebookLogin([['id' => 'page-1', 'name' => 'Main Page', 'access_token' => 'page-1-token']]);

    $this->withoutMiddleware()
        ->actingAs(facebookConnectUser())
        ->post(route('user.meta-social.setup.embedded', 'messenger'), ['code' => 'login-code'])
        ->assertRedirect(route('user.meta-social.setup'))
        ->assertSessionHas('status');

    $channel = ChannelAccount::query()->where('provider', 'messenger')->sole();

    expect($channel->provider_account_id)->toBe('page-1')
        ->and($channel->credential('access_token'))->toBe('page-1-token')
        ->and($channel->credential('source'))->toBe('embedded_signup')
        ->and($channel->status->value)->toBe('connected')
        ->and($channel->settings)->toHaveKey('webhook_subscribed_at');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/page-1/subscribed_apps')
        && $request->hasHeader('Authorization', 'Bearer page-1-token')
        && str_contains((string) $request['subscribed_fields'], 'messages'));
});

it('lets the user pick which Pages to connect when several are granted', function (): void {
    fakeFacebookLogin([
        ['id' => 'page-1', 'name' => 'Main Page', 'access_token' => 'page-1-token'],
        ['id' => 'page-2', 'name' => 'Second Page', 'access_token' => 'page-2-token'],
    ]);
    $user = facebookConnectUser();

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.setup.embedded', 'messenger'), ['code' => 'login-code'])
        ->assertRedirect();

    expect(ChannelAccount::query()->where('provider', 'messenger')->count())->toBe(0);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-social.setup'))
        ->assertOk()
        ->assertSee('Choose Facebook Pages to connect')
        ->assertSee('Second Page')
        ->assertDontSee('page-2-token');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.setup.select', 'messenger'), ['account_ids' => ['page-2', 'not-granted']])
        ->assertRedirect(route('user.meta-social.setup'));

    $channel = ChannelAccount::query()->where('provider', 'messenger')->sole();

    expect($channel->provider_account_id)->toBe('page-2')
        ->and($channel->credential('access_token'))->toBe('page-2-token');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-social.setup'))
        ->assertDontSee('Choose Facebook Pages to connect');
});

it('discovers Instagram accounts through their linked Pages', function (): void {
    fakeFacebookLogin([
        ['id' => 'page-1', 'name' => 'Main Page', 'access_token' => 'page-1-token', 'instagram_business_account' => ['id' => 'ig-1', 'username' => 'shop.one']],
        ['id' => 'page-2', 'name' => 'No Instagram', 'access_token' => 'page-2-token'],
    ]);

    $this->withoutMiddleware()
        ->actingAs(facebookConnectUser())
        ->post(route('user.meta-social.setup.embedded', 'instagram'), ['code' => 'login-code'])
        ->assertRedirect(route('user.meta-social.setup'));

    $channel = ChannelAccount::query()->where('provider', 'instagram')->sole();

    expect($channel->provider_account_id)->toBe('ig-1')
        ->and($channel->provider_phone_id)->toBe('page-1')
        ->and($channel->provider_display_id)->toBe('shop.one')
        ->and($channel->credential('access_token'))->toBe('page-1-token');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/page-1/subscribed_apps'));
});

it('flags the channel when the Page webhook subscription fails', function (): void {
    fakeFacebookLogin([['id' => 'page-1', 'name' => 'Main Page', 'access_token' => 'page-1-token']], subscribeOk: false);

    $this->withoutMiddleware()
        ->actingAs(facebookConnectUser())
        ->post(route('user.meta-social.setup.embedded', 'messenger'), ['code' => 'login-code'])
        ->assertSessionHas('error');

    $channel = ChannelAccount::query()->where('provider', 'messenger')->sole();

    expect($channel->status->value)->toBe('error')
        ->and($channel->settings['last_error'])->toContain('pages_manage_metadata');
});

it('explains when Meta shares no Pages', function (): void {
    fakeFacebookLogin([]);

    $this->withoutMiddleware()
        ->actingAs(facebookConnectUser())
        ->post(route('user.meta-social.setup.embedded', 'messenger'), ['code' => 'login-code'])
        ->assertSessionHasErrors('embedded_signup');
});

it('rejects a selection without a pending Meta login', function (): void {
    $this->withoutMiddleware()
        ->actingAs(facebookConnectUser())
        ->post(route('user.meta-social.setup.select', 'messenger'), ['account_ids' => ['page-1']])
        ->assertSessionHasErrors('embedded_signup');

    expect(ChannelAccount::query()->where('provider', 'messenger')->count())->toBe(0);
});

it('keeps the webhook verify token when a Page is reconnected', function (): void {
    fakeFacebookLogin([['id' => 'page-1', 'name' => 'Main Page', 'access_token' => 'page-1-token']]);
    $user = facebookConnectUser();

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.setup.embedded', 'messenger'), ['code' => 'login-code']);
    $firstToken = ChannelAccount::query()->where('provider', 'messenger')->sole()->webhook_verify_token;

    $this->withoutMiddleware()->actingAs($user)->post(route('user.meta-social.setup.embedded', 'messenger'), ['code' => 'login-code-2']);

    expect(ChannelAccount::query()->where('provider', 'messenger')->sole()->webhook_verify_token)->toBe($firstToken);
});
