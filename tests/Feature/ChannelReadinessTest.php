<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\User;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ChannelReadinessService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const READINESS_PANEL_MIDDLEWARE = [
    Authorize::class,
    EnsureEmailIsVerified::class,
    EnsureTwoFactorAuthenticated::class,
    PanelAccess::class,
    EnsureOnboardingComplete::class,
];

beforeEach(function (): void {
    app(WhatsAppSettingsService::class)->update(['whatsapp_graph_api_version' => 'v20.0']);
    app(MetaSocialSettingsService::class)->update(['meta_social_app_id' => 'app', 'meta_social_app_secret' => 'secret', 'meta_social_graph_api_version' => 'v20.0']);
});

function readinessChannel(string $provider, array $attributes = [], ?User $user = null): ChannelAccount
{
    $user ??= User::factory()->create(['email_verified_at' => now()]);

    return ChannelAccount::query()->create(array_merge([
        'workspace_id' => app(WorkspaceResolver::class)->current($user)->id,
        'provider' => $provider,
        'name' => ucfirst($provider).' channel',
        'status' => 'connected',
        'provider_account_id' => $provider === 'whatsapp' ? 'waba-1' : ($provider === 'instagram' ? 'ig-1' : 'page-1'),
        'provider_phone_id' => $provider === 'whatsapp' ? 'phone-1' : ($provider === 'instagram' ? 'page-1' : null),
        'credentials' => ['access_token' => 'token'],
        'webhook_verify_token' => 'verify',
        'connected_at' => now(),
    ], $attributes));
}

function fakeWhatsAppReadiness(array $phone = [], array $waba = [], ?string $funding = 'fund-1', bool $subscribed = true): void
{
    Http::fake([
        '*/phone-1*' => Http::response(array_merge([
            'id' => 'phone-1',
            'display_phone_number' => '+880 1700-000001',
            'verified_name' => 'Acme',
            'name_status' => 'APPROVED',
            'status' => 'CONNECTED',
            'quality_rating' => 'GREEN',
            'messaging_limit_tier' => 'TIER_1K',
            'health_status' => ['can_send_message' => 'AVAILABLE', 'entities' => []],
        ], $phone)),
        '*/waba-1/subscribed_apps*' => Http::response(['data' => $subscribed ? [['whatsapp_business_api_data' => ['id' => 'app']]] : []]),
        '*/waba-1?fields=primary_funding_id*' => Http::response(array_filter(['id' => 'waba-1', 'primary_funding_id' => $funding])),
        '*/waba-1*' => Http::response(array_merge(['id' => 'waba-1', 'account_review_status' => 'APPROVED', 'business_verification_status' => 'verified'], $waba)),
    ]);
}

function readinessItem(array $report, string $key): ?array
{
    return collect($report['items'])->firstWhere('key', $key);
}

it('reports a WhatsApp number as ready when Meta has nothing left', function (): void {
    fakeWhatsAppReadiness();
    $channel = readinessChannel('whatsapp');
    MessageTemplate::query()->create(['workspace_id' => $channel->workspace_id, 'provider' => 'whatsapp', 'name' => 'hello', 'language' => 'en_US', 'category' => 'utility', 'status' => 'approved']);

    $report = app(ChannelReadinessService::class)->check($channel);

    expect($report['status'])->toBe('ready')
        ->and($report['blocked_features'])->toBe([])
        ->and(collect($report['facts'])->pluck('value'))->toContain('1,000 customers / day')
        ->and(data_get($channel->fresh()->settings, 'readiness.status'))->toBe('ready')
        ->and($channel->fresh()->credential('access_token'))->toBe('token');
});

it('explains what blocks sending on a new WhatsApp number', function (): void {
    fakeWhatsAppReadiness(['name_status' => 'PENDING_REVIEW'], funding: null);

    $report = app(ChannelReadinessService::class)->check(readinessChannel('whatsapp'));

    expect(readinessItem($report, 'display_name')['state'])->toBe('waiting')
        ->and(readinessItem($report, 'payment')['state'])->toBe('action')
        ->and(readinessItem($report, 'payment')['link'])->toContain('payment_settings')
        ->and(readinessItem($report, 'templates')['state'])->toBe('action')
        ->and($report['blocked_features'])->toContain('send', 'campaigns')
        ->and($report['status'])->toBe('action')
        ->and($report['headline'])->toContain('will not work until');
});

it('does not block sending for a coexistence number that Meta only limits', function (): void {
    fakeWhatsAppReadiness(['name_status' => 'NON_EXISTS', 'health_status' => ['can_send_message' => 'LIMITED', 'entities' => []]]);
    $channel = readinessChannel('whatsapp');
    MessageTemplate::query()->create(['workspace_id' => $channel->workspace_id, 'provider' => 'whatsapp', 'name' => 'hello', 'language' => 'en_US', 'category' => 'utility', 'status' => 'approved']);

    $report = app(ChannelReadinessService::class)->check($channel);

    expect(readinessItem($report, 'display_name')['state'])->toBe('done')
        ->and(readinessItem($report, 'can_send')['state'])->toBe('action')
        ->and($report['blocked_features'])->not->toContain('send')
        ->and($report['blocked_features'])->not->toContain('templates');
});

it('shows Meta\'s own fix when a WhatsApp number is blocked', function (): void {
    fakeWhatsAppReadiness(['health_status' => [
        'can_send_message' => 'BLOCKED',
        'entities' => [[
            'entity_type' => 'WABA',
            'can_send_message' => 'BLOCKED',
            'errors' => [['error_code' => 141006, 'error_description' => 'There is no payment method.', 'possible_solution' => 'Add a payment method to the WhatsApp Business Account.']],
        ]],
    ]]);

    $report = app(ChannelReadinessService::class)->check(readinessChannel('whatsapp'));
    $item = readinessItem($report, 'can_send');

    expect($item['state'])->toBe('blocked')
        ->and(implode(' ', $item['how']))->toContain('Add a payment method to the WhatsApp Business Account.')
        ->and($report['status'])->toBe('blocked');
});

it('tells the user to reconnect when the WhatsApp token stopped working', function (): void {
    Http::fake(['*' => Http::response(['error' => ['code' => 190, 'message' => 'Error validating access token', 'fbtrace_id' => 'XYZ']], 401)]);

    $report = app(ChannelReadinessService::class)->check(readinessChannel('whatsapp'));

    expect($report['status'])->toBe('blocked')
        ->and($report['items'][0]['how'][0])->toContain('Reconnect with Meta')
        ->and($report['items'][0]['detail'])->toContain('XYZ');
});

it('blocks Messenger when the login did not grant messaging', function (): void {
    Http::fake([
        '*/debug_token*' => Http::response(['data' => ['scopes' => ['pages_show_list', 'pages_manage_metadata']]]),
        '*/page-1/subscribed_apps*' => Http::response(['data' => [['id' => 'app']]]),
        '*/page-1*' => Http::response(['id' => 'page-1', 'name' => 'Acme Page']),
    ]);

    $report = app(ChannelReadinessService::class)->check(readinessChannel('messenger', ['settings' => ['page_id' => 'page-1']]));

    expect(readinessItem($report, 'permissions')['state'])->toBe('blocked')
        ->and(readinessItem($report, 'permissions')['detail'])->toContain('pages_messaging')
        ->and(readinessItem($report, 'webhooks')['state'])->toBe('done');
});

it('asks to allow Instagram message access until the first DM arrives', function (): void {
    Http::fake([
        '*/debug_token*' => Http::response(['data' => ['scopes' => ['instagram_basic', 'instagram_manage_messages', 'pages_manage_metadata']]]),
        '*/page-1/subscribed_apps*' => Http::response(['data' => [['id' => 'app']]]),
        '*/ig-1*' => Http::response(['id' => 'ig-1', 'username' => 'acme.shop']),
    ]);
    $channel = readinessChannel('instagram', ['settings' => ['page_id' => 'page-1']]);

    $before = app(ChannelReadinessService::class)->check($channel);

    $conversation = Conversation::query()->create(['workspace_id' => $channel->workspace_id, 'channel_account_id' => $channel->id, 'provider' => 'instagram', 'status' => 'open']);
    Message::query()->create(['workspace_id' => $channel->workspace_id, 'conversation_id' => $conversation->id, 'channel_account_id' => $channel->id, 'provider' => 'instagram', 'direction' => 'inbound', 'type' => 'text', 'body' => 'hi', 'status' => 'received']);

    $after = app(ChannelReadinessService::class)->check($channel->fresh());

    expect(readinessItem($before, 'instagram_message_access')['state'])->toBe('action')
        ->and(implode(' ', readinessItem($before, 'instagram_message_access')['how']))->toContain('Allow access to messages')
        ->and(readinessItem($after, 'instagram_message_access')['state'])->toBe('done')
        ->and($after['status'])->toBe('ready');
});

it('warns before a Threads login expires', function (): void {
    Http::fake(['*graph.threads.net*' => Http::response(['id' => 'threads-1', 'username' => 'acme'])]);

    $report = app(ChannelReadinessService::class)->check(readinessChannel('threads', [
        'provider_account_id' => 'threads-1',
        'provider_phone_id' => null,
        'settings' => ['token_expires_at' => now()->addDays(3)->toISOString()],
    ]));

    expect(readinessItem($report, 'token_expiry')['state'])->toBe('action')
        ->and(readinessItem($report, 'connection')['state'])->toBe('done');
});

it('re-checks a channel from the page and shows the result', function (): void {
    fakeWhatsAppReadiness(['name_status' => 'PENDING_REVIEW']);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $channel = readinessChannel('whatsapp', [], $user);

    $this->withoutMiddleware(READINESS_PANEL_MIDDLEWARE)
        ->actingAs($user)
        ->from(route('user.whatsapp-cloud.channel-setup'))
        ->post(route('user.channels.readiness', $channel))
        ->assertRedirect(route('user.whatsapp-cloud.channel-setup'))
        ->assertSessionHas('error');

    $this->withoutMiddleware(READINESS_PANEL_MIDDLEWARE)
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('data-readiness-item="display_name"', false)
        ->assertSee('Waiting for Meta')
        ->assertSee('Display name approved');

    $this->withoutMiddleware(READINESS_PANEL_MIDDLEWARE)
        ->actingAs($user)
        ->get(route('user.channels.index'))
        ->assertOk()
        ->assertSee('data-readiness-badge', false);
});

it('does not let another workspace re-check a channel', function (): void {
    Http::fake();
    $channel = readinessChannel('whatsapp');

    $this->withoutMiddleware(READINESS_PANEL_MIDDLEWARE)
        ->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->post(route('user.channels.readiness', $channel))
        ->assertNotFound();

    Http::assertNothingSent();
});

it('re-checks every connected Meta channel on the daily run', function (): void {
    fakeWhatsAppReadiness();
    $connected = readinessChannel('whatsapp');
    $disconnected = readinessChannel('whatsapp', ['status' => 'disconnected', 'provider_phone_id' => 'phone-2']);

    expect(app(ChannelReadinessService::class)->checkConnected())->toBe(1)
        ->and(data_get($connected->fresh()->settings, 'readiness'))->not->toBeNull()
        ->and(data_get($disconnected->fresh()->settings, 'readiness'))->toBeNull();
});

it('warns in the inbox when Meta still blocks sending', function (): void {
    $channel = readinessChannel('whatsapp', ['settings' => ['readiness' => app(ChannelReadinessService::class)->summarize([
        ['key' => 'display_name', 'label' => 'Display name approved', 'state' => 'waiting', 'why' => '', 'how' => [], 'link' => null, 'link_label' => null, 'blocks' => ['send'], 'detail' => null],
    ])]]);
    $conversation = Conversation::query()->create(['workspace_id' => $channel->workspace_id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'status' => 'open']);

    $formatted = (fn () => $this->formatConversation($conversation->fresh('channelAccount')))->call(app(InboxService::class));

    expect($formatted['channel_warning']['message'])->toContain('Display name approved')
        ->and($formatted['channel_warning']['url'])->toBe(route('user.whatsapp-cloud.channel-setup'));
});

it('turns a WhatsApp send failure into a plain-language error with a next step', function (): void {
    Http::fake(['*/phone-1/messages' => Http::response(['error' => ['code' => 131047, 'message' => 'Re-engagement message', 'fbtrace_id' => 'TR1']], 400)]);

    $result = app(ChannelManager::class)
        ->sendMessage(readinessChannel('whatsapp'), ['to' => '8801700000001'], ['type' => 'text', 'body' => 'Hello']);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toContain('More than 24 hours passed')
        ->and($result['error_detail']['window'])->toBeTrue()
        ->and($result['error_detail']['trace_id'])->toBe('TR1')
        ->and($result['response']['error']['message'])->toBe('Re-engagement message');
});
