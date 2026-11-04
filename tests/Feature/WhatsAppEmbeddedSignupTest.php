<?php

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\WhatsAppCloud\Services\ChannelSetupService;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(WhatsAppSettingsService::class)->update([
        'whatsapp_embedded_signup_enabled' => true,
        'whatsapp_meta_app_id' => 'app-id',
        'whatsapp_meta_app_secret' => 'app-secret',
        'whatsapp_embedded_signup_config_id' => 'config-id',
        'whatsapp_auto_sync_phone_numbers' => true,
        'whatsapp_auto_sync_templates' => false,
    ]);
});

function fakeEmbeddedSignupGraph(bool $subscribeOk = true): void
{
    Http::fake([
        '*/oauth/access_token*' => Http::response(['access_token' => 'EAAexchanged', 'token_type' => 'bearer', 'expires_in' => 5184000]),
        '*/waba-1/phone_numbers*' => Http::response(['data' => [
            ['id' => 'phone-1', 'display_phone_number' => '+880 1700-000001', 'verified_name' => 'Primary Line'],
            ['id' => 'phone-2', 'display_phone_number' => '+880 1700-000002', 'verified_name' => 'Second Line'],
        ]]),
        '*/phone-1/register' => Http::response(['success' => true]),
        '*/waba-1/subscribed_apps' => $subscribeOk
            ? Http::response(['success' => true])
            : Http::response(['error' => ['message' => 'Missing permission']], 400),
    ]);
}

it('connects with a token returned directly by Meta instead of a code', function (): void {
    fakeEmbeddedSignupGraph();

    $result = app(ChannelSetupService::class)->connectFromEmbeddedSignup(User::factory()->create(), [
        'code' => 'EAAdirect-token',
        'waba_id' => 'waba-1',
        'phone_number_id' => 'phone-1',
        'signup_mode' => 'coexistence',
    ]);

    expect($result['channel']->credential('access_token'))->toBe('EAAdirect-token')
        ->and($result['channel']->status->value ?? $result['channel']->status)->toBe(ChannelAccountStatus::Connected->value);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'oauth/access_token'));
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/register'));
});

it('registers new numbers and keeps the channel on the selected number', function (): void {
    fakeEmbeddedSignupGraph();

    $channel = app(ChannelSetupService::class)->connectFromEmbeddedSignup(User::factory()->create(), [
        'code' => 'auth-code',
        'waba_id' => 'waba-1',
        'phone_number_id' => 'phone-1',
        'signup_mode' => 'new',
    ])['channel'];

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/phone-1/register')
        && $request['messaging_product'] === 'whatsapp'
        && preg_match('/^\d{6}$/', (string) $request['pin']) === 1);

    expect(ChannelAccount::query()->where('workspace_id', $channel->workspace_id)->where('provider', 'whatsapp')->count())->toBe(1)
        ->and($channel->provider_phone_id)->toBe('phone-1')
        ->and($channel->name)->toBe('Primary Line')
        ->and($channel->credential('access_token'))->toBe('EAAexchanged')
        ->and($channel->credential('two_step_pin'))->toMatch('/^\d{6}$/')
        ->and($channel->settings['registered_phone_id'])->toBe('phone-1')
        ->and($channel->settings['other_phone_numbers'][0]['id'])->toBe('phone-2');
});

it('marks the channel as errored when the webhook subscription fails', function (): void {
    fakeEmbeddedSignupGraph(subscribeOk: false);

    $channel = app(ChannelSetupService::class)->connectFromEmbeddedSignup(User::factory()->create(), [
        'code' => 'auth-code',
        'waba_id' => 'waba-1',
        'phone_number_id' => 'phone-1',
        'signup_mode' => 'coexistence',
    ])['channel'];

    expect($channel->status->value ?? $channel->status)->toBe(ChannelAccountStatus::Error->value)
        ->and($channel->settings['last_error'])->toContain('Missing permission');
});

it('has no manual WhatsApp credential form or route', function (): void {
    expect(Route::has('user.whatsapp-cloud.channel-setup.store'))->toBeFalse();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('data-meta-connect="whatsapp"', false)
        ->assertDontSee('name="access_token"', false);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('data-meta-connect-link', false)
        ->assertDontSee('name="access_token"', false)
        ->assertDontSee('name="waba_id" type="text"', false)
        ->assertDontSee('Set up manually');
});
