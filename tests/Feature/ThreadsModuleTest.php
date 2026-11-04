<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Services\MetaAppSettingsService;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function threadsWorkspaceFor(User $user)
{
    return app(WorkspaceResolver::class)->current($user);
}

function withoutThreadsPanelMiddleware($test): mixed
{
    return $test->withoutMiddleware([
        Authorize::class,
        EnsureEmailIsVerified::class,
        EnsureTwoFactorAuthenticated::class,
        PanelAccess::class,
        EnsureOnboardingComplete::class,
    ]);
}

it('registers the Threads marketing channel driver', function (): void {
    expect(app(ChannelManager::class)->providers())->toContain('threads');
});

it('connects a Threads channel through Threads login and shows test action', function (): void {
    app(MetaAppSettingsService::class)->update(['meta_threads_app_id' => 'threads-app', 'meta_threads_app_secret' => 'threads-secret']);

    Http::fake([
        'https://graph.threads.net/oauth/access_token' => Http::response(['access_token' => 'short-token', 'user_id' => 'threads-user-123']),
        'https://graph.threads.net/access_token*' => Http::response(['access_token' => 'long-token', 'expires_in' => 5184000]),
        'https://graph.threads.net/v1.0/me*' => Http::response(['id' => 'threads-user-123', 'username' => 'brand']),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    threadsWorkspaceFor($user);

    $redirect = withoutThreadsPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.threads.connect'))
        ->assertRedirect()
        ->headers->get('Location');

    parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $query);

    expect($redirect)->toStartWith('https://threads.net/oauth/authorize')
        ->and($query['client_id'])->toBe('threads-app')
        ->and($query['redirect_uri'])->toBe(route('user.threads.callback'));

    withoutThreadsPanelMiddleware($this)
        ->actingAs($user)
        ->get(route('user.threads.callback', ['code' => 'auth-code#_', 'state' => $query['state']]))
        ->assertRedirect(route('user.meta-social.setup').'#threads')
        ->assertSessionHas('status');

    $channel = ChannelAccount::query()->where('provider', 'threads')->firstOrFail();

    expect($channel->status->value)->toBe('connected')
        ->and($channel->provider_account_id)->toBe('threads-user-123')
        ->and($channel->provider_display_id)->toBe('@brand')
        ->and($channel->credential('access_token'))->toBe('long-token')
        ->and($channel->credential('source'))->toBe('threads_login');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.threads.net/oauth/access_token' && $request['code'] === 'auth-code');

    withoutThreadsPanelMiddleware($this)
        ->actingAs($user)
        ->get(route('user.meta-social.setup'))
        ->assertOk()
        ->assertSee('Threads')
        ->assertSee('Test Connection')
        ->assertDontSee('name="access_token"', false);
});

it('rejects a Threads callback with a forged state', function (): void {
    app(MetaAppSettingsService::class)->update(['meta_threads_app_id' => 'threads-app', 'meta_threads_app_secret' => 'threads-secret']);
    Http::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);
    threadsWorkspaceFor($user);

    withoutThreadsPanelMiddleware($this)->actingAs($user)->post(route('user.threads.connect'));

    withoutThreadsPanelMiddleware($this)
        ->actingAs($user)
        ->get(route('user.threads.callback', ['code' => 'auth-code', 'state' => 'forged']))
        ->assertSessionHas('error');

    expect(ChannelAccount::query()->where('provider', 'threads')->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('does not start Threads login before the admin adds the Threads app', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    threadsWorkspaceFor($user);

    withoutThreadsPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.threads.connect'))
        ->assertRedirect(route('user.meta-social.setup').'#threads')
        ->assertSessionHas('error');
});

it('tests a Threads connection through Channel Setup', function (): void {
    Http::fake([
        'https://graph.threads.net/v1.0/threads-user-123*' => Http::response([
            'id' => 'threads-user-123',
            'username' => 'brand',
        ]),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = threadsWorkspaceFor($user);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'threads',
        'name' => 'Brand Threads',
        'status' => 'connected',
        'credentials' => ['access_token' => 'threads-token'],
        'webhook_verify_token' => 'threads-verify',
        'provider_account_id' => 'threads-user-123',
        'provider_display_id' => '@brand',
    ]);

    withoutThreadsPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.test-channel', $channel))
        ->assertRedirect()
        ->assertSessionHas('status', 'Connection successful.');
});

it('publishes a Threads text post through the channel driver', function (): void {
    Http::fake([
        'https://graph.threads.net/v1.0/threads-user-123/threads' => Http::response(['id' => 'creation-123']),
        'https://graph.threads.net/v1.0/threads-user-123/threads_publish' => Http::response(['id' => 'post-123']),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = threadsWorkspaceFor($user);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'threads',
        'name' => 'Brand Threads',
        'status' => 'connected',
        'credentials' => ['access_token' => 'threads-token'],
        'provider_account_id' => 'threads-user-123',
    ]);

    $result = app(ChannelManager::class)->sendMessage($channel, [], ['body' => 'New product update']);

    expect($result['ok'])->toBeTrue()
        ->and($result['provider_message_id'])->toBe('post-123')
        ->and($result['status'])->toBe('sent');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.threads.net/v1.0/threads-user-123/threads'
        && $request['text'] === 'New product update'
        && ! isset($request['reply_to_id']));
});

it('normalizes Threads webhook payloads', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = threadsWorkspaceFor($user);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'threads',
        'name' => 'Brand Threads',
        'status' => 'connected',
        'credentials' => ['access_token' => 'threads-token'],
        'provider_account_id' => 'threads-user-123',
    ]);

    $result = app(ChannelManager::class)->processWebhook($channel, [
        'entry' => [[
            'id' => 'threads-user-123',
            'changes' => [[
                'value' => [
                    'id' => 'reply-123',
                    'text' => 'Nice update',
                    'from' => [
                        'id' => 'threads-user-456',
                        'username' => 'nice_customer',
                        'name' => 'Nice Customer',
                    ],
                ],
            ]],
        ]],
    ]);

    expect($result['ok'])->toBeTrue()
        ->and($result['events'])->toHaveCount(1)
        ->and($result['events'][0])->toMatchArray([
            'type' => 'message',
            'provider_message_id' => 'reply-123',
            'provider_contact_id' => 'threads-user-456',
            'username' => 'nice_customer',
            'name' => 'Nice Customer',
            'body' => 'Nice update',
        ]);
});
