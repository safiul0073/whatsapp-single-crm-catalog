<?php

use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Workspace}
 */
function channelHubUser(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    return [$user, app(WorkspaceResolver::class)->current($user)];
}

function channelHubAccount(Workspace $workspace, string $provider, string $name, string $status): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => $provider,
        'name' => $name,
        'status' => $status,
        'provider_account_id' => $provider.'-account',
        'provider_display_id' => $name,
        'connected_at' => $status === 'connected' ? now() : null,
    ]);
}

it('registers the channel hub and social setup routes', function (): void {
    foreach ([
        'user.channels.index',
        'user.meta-social.setup',
        'user.meta-social.setup.embedded',
        'user.meta-social.setup.disconnect',
    ] as $route) {
        expect(Route::has($route))->toBeTrue($route.' should be registered');
    }
});

it('lists every connectable provider with a set up link when nothing is connected', function (): void {
    [$user] = channelHubUser();

    $response = $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('Not connected')
        ->assertSee('Set up')
        ->assertSee(route('user.whatsapp-cloud.channel-setup'))
        ->assertSee(route('user.email.index'))
        ->assertSee(route('user.sms.index'))
        ->assertSee(route('user.telegram.index'));

    foreach (['whatsapp', 'telegram', 'email', 'sms', 'messenger', 'instagram', 'threads'] as $provider) {
        $response
            ->assertSee('data-channel-card="'.$provider.'"', false)
            ->assertSee('data-channel-setup-link="'.$provider.'"', false);
    }
});

it('shows connection status per provider and manage links for existing channels', function (): void {
    [$user, $workspace] = channelHubUser();
    channelHubAccount($workspace, 'whatsapp', 'Main Line', 'connected');
    channelHubAccount($workspace, 'telegram', 'Support Bot', 'error');
    channelHubAccount($workspace, 'email', 'Newsletter Sender', 'disconnected');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('data-channel-status="connected"', false)
        ->assertSee('data-channel-status="error"', false)
        ->assertSee('data-channel-status="disconnected"', false)
        ->assertSee('Main Line')
        ->assertSee('Support Bot')
        ->assertSee('Newsletter Sender')
        ->assertSee('Manage')
        ->assertSee('1 channel connected');
});

it('shows internal widget channels without a setup action', function (): void {
    [$user, $workspace] = channelHubUser();
    channelHubAccount($workspace, 'website_widget', 'Homepage Widget', 'connected');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('Homepage Widget')
        ->assertSee('Website Chat Widget')
        ->assertSee('Managed automatically')
        ->assertDontSee('data-channel-setup-link="website_widget"', false)
        ->assertDontSee('Connect Website widget')
        ->assertDontSee('Update Website widget');
});

it('links every channel setup page back to the hub with guided steps', function (string $routeName): void {
    [$user] = channelHubUser();

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('data-channel-hub-link', false)
        ->assertSee(route('user.channels.index'))
        ->assertSee('data-setup-steps', false)
        ->assertSee('All channels');
})->with([
    'user.whatsapp-cloud.channel-setup',
    'user.telegram.index',
    'user.email.index',
    'user.sms.index',
    'user.meta-social.setup',
]);
