<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
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

it('connects a Threads channel from Channel Setup and shows test action', function (): void {
    Http::fake([
        'https://graph.threads.net/v1.0/threads-user-123*' => Http::response([
            'id' => 'threads-user-123',
            'username' => 'brand',
        ]),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    threadsWorkspaceFor($user);

    withoutThreadsPanelMiddleware($this)
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.store-generic'), [
            'provider' => 'threads',
            'name' => 'Brand Threads',
            'provider_account_id' => 'threads-user-123',
            'provider_display_id' => '@brand',
            'access_token' => 'threads-token',
            'webhook_verify_token' => 'threads-verify',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $channel = ChannelAccount::query()->where('provider', 'threads')->firstOrFail();

    expect($channel->status->value)->toBe('connected')
        ->and($channel->provider_account_id)->toBe('threads-user-123')
        ->and($channel->provider_display_id)->toBe('@brand')
        ->and($channel->credential('access_token'))->toBe('threads-token');

    withoutThreadsPanelMiddleware($this)
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('Threads')
        ->assertSee('Test Connection');
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
