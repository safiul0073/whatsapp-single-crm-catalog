<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\User;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function metaSocialWorkspace(User $user): Workspace
{
    return Workspace::query()->create([
        'owner_id' => $user->id,
        'name' => 'WaPro Workspace',
        'slug' => 'wapro-workspace-'.$user->id,
        'status' => 'active',
        'timezone' => 'UTC',
        'settings' => [],
    ]);
}

it('registers Meta social user admin and webhook routes', function (): void {
    expect(Route::has('user.meta-social.setup'))->toBeTrue()
        ->and(Route::has('user.meta-social.setup.embedded'))->toBeTrue()
        ->and(Route::has('user.whatsapp-cloud.channel-setup.test-channel'))->toBeTrue()
        ->and(Route::has('admin.meta-social.settings.index'))->toBeTrue()
        ->and(Route::has('webhooks.meta.verify'))->toBeTrue()
        ->and(Route::has('webhooks.meta.receive'))->toBeTrue();
});

it('renders Messenger and Instagram setup options without exposing credentials', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'messenger',
        'name' => 'WaPro Support',
        'status' => 'connected',
        'credentials' => ['access_token' => 'page-secret-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'page-123',
        'settings' => ['inbox_active' => true],
        'connected_at' => now(),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-social.setup'))
        ->assertOk()
        ->assertSee('Social Channels')
        ->assertSee('Messenger')
        ->assertSee('Instagram')
        ->assertSee('Connect with Meta')
        ->assertSee(route('user.channels.index').'#meta-connect', false)
        ->assertSee('WaPro Support')
        ->assertDontSee('page-secret-token');
});

it('enables embedded signup config when admin settings are complete', function (): void {
    app(MetaSocialSettingsService::class)->update([
        'meta_social_embedded_signup_enabled' => true,
        'meta_social_app_id' => 'meta-app-id',
        'meta_social_app_secret' => 'meta-secret',
        'meta_social_messenger_config_id' => 'messenger-config',
        'meta_social_instagram_config_id' => 'instagram-config',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('data-meta-connect="messenger"', false)
        ->assertSee('messenger-config')
        ->assertSee('instagram-config');
});

it('connects Messenger from embedded signup code exchange', function (): void {
    app(MetaSocialSettingsService::class)->update([
        'meta_social_embedded_signup_enabled' => true,
        'meta_social_app_id' => 'meta-app-id',
        'meta_social_app_secret' => 'meta-secret',
        'meta_social_messenger_config_id' => 'messenger-config',
    ]);

    Http::fake([
        'https://graph.facebook.com/v20.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'https://graph.facebook.com/v20.0/me/accounts*' => Http::response(['data' => [['id' => 'page-123', 'name' => 'WaPro Support', 'access_token' => 'page-token']]]),
        'https://graph.facebook.com/v20.0/page-123/subscribed_apps*' => Http::response(['success' => true]),
        'https://graph.facebook.com/v20.0/page-123*' => Http::response(['id' => 'page-123', 'name' => 'WaPro Support']),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.setup.embedded', 'messenger'), ['code' => 'embedded-code'])
        ->assertRedirect()
        ->assertSessionHas('status');

    $channel = ChannelAccount::query()->where('provider', 'messenger')->firstOrFail();

    expect($channel->provider_account_id)->toBe('page-123')
        ->and($channel->name)->toBe('WaPro Support')
        ->and($channel->credential('access_token'))->toBe('page-token')
        ->and($channel->credential('source'))->toBe('embedded_signup')
        ->and($channel->status->value)->toBe('connected');
});

it('rejects a pasted Instagram token without a Meta login code', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.setup.embedded', 'instagram'), [
            'access_token' => 'instagram-token',
            'instagram_account_id' => 'ig-123',
            'username' => 'wapro.app',
        ])
        ->assertSessionHasErrors('code');

    expect(ChannelAccount::query()->where('provider', 'instagram')->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('refuses to save Meta channels from pasted credentials', function (string $provider): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    metaSocialWorkspace($user);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.store-generic'), [
            'provider' => $provider,
            'name' => 'Pasted channel',
            'provider_account_id' => 'account-123',
            'access_token' => 'pasted-token',
        ])
        ->assertSessionHasErrors('provider');

    expect(ChannelAccount::query()->where('provider', $provider)->exists())->toBeFalse();
    Http::assertNothingSent();
})->with(['whatsapp', 'messenger', 'instagram', 'threads']);

it('tests Messenger connection through Channel Setup', function (): void {
    Http::fake([
        'https://graph.facebook.com/v20.0/page-123*' => Http::response(['id' => 'page-123', 'name' => 'WaPro Support']),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'messenger',
        'name' => 'WaPro Support',
        'status' => 'connected',
        'credentials' => ['access_token' => 'page-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'page-123',
    ]);

    $this->withoutMiddleware([
        Authorize::class,
        EnsureEmailIsVerified::class,
        EnsureTwoFactorAuthenticated::class,
        PanelAccess::class,
        EnsureOnboardingComplete::class,
    ])
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.test-channel', $channel))
        ->assertRedirect()
        ->assertSessionHas('status', 'Connection successful.');
});

it('tests Instagram connection through Channel Setup', function (): void {
    Http::fake([
        'https://graph.facebook.com/v20.0/ig-123*' => Http::response(['id' => 'ig-123', 'username' => 'wapro.app']),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'instagram',
        'name' => 'WaPro Instagram',
        'status' => 'connected',
        'credentials' => ['access_token' => 'instagram-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'ig-123',
    ]);

    $this->withoutMiddleware([
        Authorize::class,
        EnsureEmailIsVerified::class,
        EnsureTwoFactorAuthenticated::class,
        PanelAccess::class,
        EnsureOnboardingComplete::class,
    ])
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.test-channel', $channel))
        ->assertRedirect()
        ->assertSessionHas('status', 'Connection successful.');
});

it('shows Messenger validation errors on Channel Setup', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'messenger',
        'name' => 'WaPro Support',
        'status' => 'error',
        'credentials' => ['access_token' => 'bad-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'page-123',
        'settings' => ['last_error' => 'Invalid OAuth access token.'],
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-social.setup'))
        ->assertOk()
        ->assertSee('Messenger')
        ->assertSee('Invalid OAuth access token.')
        ->assertSee('Test Connection');
});

it('shows Instagram validation errors on Channel Setup', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'instagram',
        'name' => 'WaPro Instagram',
        'status' => 'error',
        'credentials' => ['access_token' => 'bad-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'ig-123',
        'settings' => ['last_error' => 'Unsupported get request.'],
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-social.setup'))
        ->assertOk()
        ->assertSee('Instagram')
        ->assertSee('Unsupported get request.')
        ->assertSee('Test Connection');
});

it('verifies a Meta webhook by token', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'messenger',
        'name' => 'WaPro Support',
        'status' => 'connected',
        'credentials' => ['access_token' => 'page-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'page-123',
    ]);

    $this->get('/webhooks/meta/verify-token?hub.mode=subscribe&hub.verify_token=verify-token&hub.challenge=challenge-token')
        ->assertOk()
        ->assertSee('challenge-token');
});

it('verifies a Messenger channel webhook by dynamic code', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'messenger',
        'name' => 'WaPro Support',
        'status' => 'connected',
        'credentials' => ['access_token' => 'page-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'page-123',
    ]);

    $this->get(route('webhooks.channels.account.verify', [
        'provider' => 'messenger',
        'webhookCode' => $channel->webhook_code,
        'hub.mode' => 'subscribe',
        'hub.verify_token' => 'verify-token',
        'hub.challenge' => 'dynamic-challenge',
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('dynamic-challenge');

    $this->get(route('webhooks.channels.account.verify', [
        'provider' => 'messenger',
        'webhookCode' => $channel->webhook_code,
        'hub.mode' => 'subscribe',
        'hub.verify_token' => 'wrong-token',
        'hub.challenge' => 'dynamic-challenge',
    ]))->assertForbidden();
});

it('persists inbound Messenger webhook messages into shared inbox tables', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'messenger',
        'name' => 'WaPro Support',
        'status' => 'connected',
        'credentials' => ['access_token' => 'page-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => 'page-123',
    ]);

    $this->postJson('/webhooks/meta/verify-token', [
        'object' => 'page',
        'entry' => [[
            'id' => 'page-123',
            'time' => 1710000000,
            'messaging' => [[
                'sender' => ['id' => 'psid-123'],
                'recipient' => ['id' => 'page-123'],
                'timestamp' => 1710000001,
                'message' => ['mid' => 'mid-123', 'text' => 'Hi there'],
            ]],
        ]],
    ])->assertOk();

    expect(ContactProviderIdentity::query()->where('provider_contact_id', 'psid-123')->exists())->toBeTrue()
        ->and(Conversation::query()->where('provider', 'messenger')->exists())->toBeTrue()
        ->and(Message::query()->where('provider_message_id', 'mid-123')->where('body', 'Hi there')->exists())->toBeTrue();
});

it('persists inbound Instagram webhook messages into shared inbox tables', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'instagram',
        'name' => 'WaPro Instagram',
        'status' => 'connected',
        'credentials' => ['access_token' => 'ig-token'],
        'webhook_verify_token' => 'ig-token',
        'provider_account_id' => 'ig-123',
    ]);

    $this->postJson('/webhooks/meta/ig-token', [
        'object' => 'instagram',
        'entry' => [[
            'id' => 'ig-123',
            'time' => 1710000000,
            'messaging' => [[
                'sender' => ['id' => 'ig-user-123'],
                'recipient' => ['id' => 'ig-123'],
                'timestamp' => 1710000001,
                'message' => ['mid' => 'ig-mid-123', 'text' => 'Need price'],
            ]],
        ]],
    ])->assertOk();

    expect(ContactProviderIdentity::query()->where('provider_contact_id', 'ig-user-123')->exists())->toBeTrue()
        ->and(Conversation::query()->where('provider', 'instagram')->exists())->toBeTrue()
        ->and(Message::query()->where('provider_message_id', 'ig-mid-123')->where('body', 'Need price')->exists())->toBeTrue();
});

it('persists inbound Instagram comments and replies to them publicly', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'reply-1'])]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaSocialWorkspace($user);

    $account = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'instagram',
        'name' => 'WaPro Instagram',
        'status' => 'connected',
        'credentials' => ['access_token' => 'ig-token'],
        'webhook_verify_token' => 'ig-token',
        'provider_account_id' => 'ig-123',
    ]);

    $comment = fn (string $id, string $fromId): array => [
        'object' => 'instagram',
        'entry' => [[
            'id' => 'ig-123',
            'time' => 1710000000,
            'changes' => [[
                'field' => 'comments',
                'value' => [
                    'id' => $id,
                    'text' => 'How much?',
                    'from' => ['id' => $fromId, 'username' => 'buyer'],
                    'media' => ['id' => 'media-1'],
                ],
            ]],
        ]],
    ];

    $this->postJson('/webhooks/meta/ig-token', $comment('comment-1', 'ig-user-9'))->assertOk();
    $this->postJson('/webhooks/meta/ig-token', $comment('comment-2', 'ig-123'))->assertOk();

    expect(Message::query()->where('provider_message_id', 'comment-1')->where('type', 'comment')->where('body', 'How much?')->exists())->toBeTrue()
        ->and(Message::query()->where('provider_message_id', 'comment-2')->exists())->toBeFalse();

    $result = app(InboxService::class)->sendMessage(
        $user,
        Conversation::query()->where('provider', 'instagram')->value('id'),
        'Check your DMs!'
    );

    expect($result['ok'])->toBeTrue();
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/comment-1/replies') && $request['message'] === 'Check your DMs!');
});

it('stores a never-expiring page token after the long-lived token exchange', function (string $provider, string $accountId, array $page): void {
    app(MetaSocialSettingsService::class)->update([
        'meta_social_embedded_signup_enabled' => true,
        'meta_social_app_id' => 'meta-app-id',
        'meta_social_app_secret' => 'meta-secret',
        'meta_social_messenger_config_id' => 'messenger-config',
        'meta_social_instagram_config_id' => 'instagram-config',
    ]);

    Http::fake([
        'https://graph.facebook.com/v20.0/oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'short-user-token'])
            ->push(['access_token' => 'long-user-token']),
        'https://graph.facebook.com/v20.0/me/accounts*' => Http::response(['data' => [
            $page,
        ]]),
        'https://graph.facebook.com/v20.0/*/subscribed_apps*' => Http::response(['success' => true]),
        'https://graph.facebook.com/v20.0/'.$accountId.'*' => Http::response(['id' => $accountId, 'name' => 'Shop', 'username' => 'shop']),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.setup.embedded', $provider), ['code' => 'embedded-code'])
        ->assertRedirect();

    $channel = ChannelAccount::query()->where('provider', $provider)->firstOrFail();

    expect($channel->credential('access_token'))->toBe('page-access-token')
        ->and($channel->status->value)->toBe('connected');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'oauth/access_token')
        && ($request['grant_type'] ?? null) === 'fb_exchange_token'
        && $request['fb_exchange_token'] === 'short-user-token');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'me/accounts')
        && $request->hasHeader('Authorization', 'Bearer long-user-token'));
})->with([
    'messenger' => ['messenger', 'page-123', ['id' => 'page-123', 'access_token' => 'page-access-token']],
    'instagram' => ['instagram', 'ig-123', ['id' => 'page-9', 'access_token' => 'page-access-token', 'instagram_business_account' => ['id' => 'ig-123']]],
]);

it('saves nothing when Meta shares no Page during login', function (): void {
    app(MetaSocialSettingsService::class)->update([
        'meta_social_embedded_signup_enabled' => true,
        'meta_social_app_id' => 'meta-app-id',
        'meta_social_app_secret' => 'meta-secret',
        'meta_social_messenger_config_id' => 'messenger-config',
    ]);

    Http::fake([
        'https://graph.facebook.com/v20.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'https://graph.facebook.com/v20.0/me/accounts*' => Http::response(['data' => []]),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.meta-social.setup.embedded', 'messenger'), ['code' => 'embedded-code'])
        ->assertSessionHasErrors('embedded_signup');

    expect(ChannelAccount::query()->where('provider', 'messenger')->exists())->toBeFalse();
});
