<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaAds\Models\MetaAdReferral;
use App\Modules\MetaSocial\Models\MetaBusinessConnection;
use App\Modules\MetaSocial\Services\MetaSocialClient;
use App\Modules\MetaSocial\Services\MetaSocialDriver;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudClient;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;
use App\Modules\WhatsAppCloud\Services\WhatsAppMessagePayloadBuilder;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function metaAdsWorkspace(User $user): Workspace
{
    return app(WorkspaceResolver::class)->current($user);
}

function metaAdsChannel(Workspace $workspace, string $provider): ChannelAccount
{
    return ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => $provider,
        'name' => ucfirst($provider).' line',
        'status' => 'connected',
        'provider_account_id' => $provider === 'whatsapp' ? 'waba-ads' : 'page-ads',
        'provider_phone_id' => $provider === 'whatsapp' ? 'phone-ads' : 'page-ads',
        'credentials' => ['access_token' => 'token'],
        'webhook_verify_token' => 'verify',
    ]);
}

function whatsappAdWebhook(array $message): Request
{
    return Request::create('/webhooks/channels/whatsapp', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
        'entry' => [[
            'id' => 'waba-ads',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => 'phone-ads'],
                    'contacts' => [['wa_id' => '8801811111111', 'profile' => ['name' => 'Rina']]],
                    'messages' => [array_merge([
                        'id' => 'wamid.'.Str::random(8),
                        'from' => '8801811111111',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'Is this dress available?'],
                    ], $message)],
                ],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));
}

function whatsappAdDriver(): WhatsAppCloudDriver
{
    return new WhatsAppCloudDriver(app(WhatsAppCloudClient::class), new WhatsAppMessagePayloadBuilder);
}

it('records the ad behind a click-to-WhatsApp chat', function (): void {
    $channel = metaAdsChannel(metaAdsWorkspace(User::factory()->create()), 'whatsapp');

    whatsappAdDriver()->handleWebhook(whatsappAdWebhook([
        'referral' => [
            'source_url' => 'https://fb.me/abc',
            'source_id' => '120200000001',
            'source_type' => 'ad',
            'headline' => 'Eid Collection',
            'body' => '30% off this week',
            'media_type' => 'image',
            'ctwa_clid' => 'ARAkLkA8rmlFeiCktEJQ',
        ],
    ]), $channel);

    $referral = MetaAdReferral::query()->sole();

    expect($referral->ad_id)->toBe('120200000001')
        ->and($referral->ctwa_clid)->toBe('ARAkLkA8rmlFeiCktEJQ')
        ->and($referral->headline)->toBe('Eid Collection')
        ->and($referral->provider)->toBe('whatsapp')
        ->and($referral->conversation_id)->not->toBeNull()
        ->and($referral->contact_id)->not->toBeNull();
});

it('ignores post referrals and ordinary messages', function (): void {
    $channel = metaAdsChannel(metaAdsWorkspace(User::factory()->create()), 'whatsapp');

    whatsappAdDriver()->handleWebhook(whatsappAdWebhook([
        'referral' => ['source_type' => 'post', 'source_id' => '999', 'source_url' => 'https://fb.me/post'],
    ]), $channel);
    whatsappAdDriver()->handleWebhook(whatsappAdWebhook([]), $channel);

    expect(MetaAdReferral::query()->count())->toBe(0);
});

it('records click-to-Messenger ad referrals', function (): void {
    $channel = metaAdsChannel(metaAdsWorkspace(User::factory()->create()), 'messenger');
    $driver = new MetaSocialDriver(app(MetaSocialClient::class), 'messenger');

    $driver->handleWebhook(Request::create('/webhooks/meta/verify', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
        'object' => 'page',
        'entry' => [[
            'id' => 'page-ads',
            'time' => now()->timestamp,
            'messaging' => [[
                'sender' => ['id' => 'psid-1'],
                'recipient' => ['id' => 'page-ads'],
                'message' => ['mid' => 'm_1', 'text' => 'Hi, price?'],
                'referral' => ['source' => 'ADS', 'type' => 'OPEN_THREAD', 'ad_id' => '120200000002', 'ads_context_data' => ['ad_title' => 'Winter Sale']],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR)), $channel);
    app()->terminate();

    $referral = MetaAdReferral::query()->sole();

    expect($referral->ad_id)->toBe('120200000002')
        ->and($referral->headline)->toBe('Winter Sale')
        ->and($referral->provider)->toBe('messenger');
});

it('shows Meta ad results next to chats and orders the ads brought in', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = metaAdsWorkspace($user);
    $contact = Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Rina', 'phone' => '+8801811111111']);

    MetaBusinessConnection::query()->create([
        'workspace_id' => $workspace->id,
        'business_name' => 'Acme Fashion',
        'credentials' => ['access_token' => 'EAAbusiness'],
        'assets' => ['ad_accounts' => [['id' => 'act_1', 'name' => 'Acme Ads', 'currency' => 'BDT']]],
        'selected' => ['ad_accounts' => ['act_1']],
        'connected_at' => now(),
    ]);
    MetaAdReferral::query()->create([
        'workspace_id' => $workspace->id,
        'message_id' => 1,
        'conversation_id' => 10,
        'contact_id' => $contact->id,
        'provider' => 'whatsapp',
        'ad_id' => '120200000001',
        'headline' => 'Eid Collection',
        'referred_at' => now()->subDays(2),
    ]);

    $order = fn (string $total, string $status, $createdAt): Order => tap(Order::query()->create([
        'workspace_id' => $workspace->id,
        'contact_id' => $contact->id,
        'source' => 'whatsapp',
        'submission_reference' => (string) Str::uuid(),
        'payload_hash' => Str::random(8),
        'number' => 'ORD-'.Str::random(6),
        'tracking_code' => 'TRK-'.Str::random(6),
        'status' => $status,
        'currency' => 'BDT',
        'subtotal' => $total,
        'total' => $total,
        'customer_snapshot' => [],
        'shipping_address' => [],
    ]), fn (Order $order) => $order->forceFill(['created_at' => $createdAt])->save());

    $order('1500.50', 'pending', now()->subDay());
    $order('999.00', 'cancelled', now()->subDay());
    $order('700.00', 'pending', now()->subDays(5));

    Http::fake([
        '*/act_1/insights*' => function ($request) {
            return str_contains((string) $request->url(), 'level=campaign')
                ? Http::response(['data' => [['campaign_id' => 'c1', 'campaign_name' => 'Eid CTWA', 'spend' => '500.00', 'clicks' => '40', 'actions' => [['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '12']]]]])
                : Http::response(['data' => [[
                    'spend' => '500.00',
                    'impressions' => '10000',
                    'clicks' => '40',
                    'account_currency' => 'BDT',
                    'actions' => [
                        ['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '12'],
                        ['action_type' => 'omni_purchase', 'value' => '3'],
                        ['action_type' => 'purchase', 'value' => '3'],
                    ],
                    'action_values' => [['action_type' => 'omni_purchase', 'value' => '4500.00']],
                    'purchase_roas' => [['action_type' => 'omni_purchase', 'value' => '9.0']],
                ]]]);
        },
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-ads.index', ['period' => '30d']))
        ->assertOk()
        ->assertSee('Acme Ads')
        ->assertSee('BDT 500.00')
        ->assertSee('Eid CTWA')
        ->assertSee('9.00×')
        ->assertSee('Eid Collection')
        ->assertSee('BDT 1,500.50')
        ->assertDontSee('2,200.50')
        ->assertDontSee('EAAbusiness');
});

it('shows the Meta error for an ad account without ads access', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    MetaBusinessConnection::query()->create([
        'workspace_id' => metaAdsWorkspace($user)->id,
        'credentials' => ['access_token' => 'EAAbusiness'],
        'assets' => ['ad_accounts' => [['id' => 'act_2', 'name' => 'Locked Ads']]],
        'selected' => ['ad_accounts' => ['act_2']],
    ]);

    Http::fake(['*/act_2/insights*' => Http::response(['error' => ['message' => '(#200) Requires ads_read permission']], 403)]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.meta-ads.index'))
        ->assertOk()
        ->assertSee('Locked Ads')
        ->assertSee('Requires ads_read permission');
});

it('asks to connect Meta Business when nothing is connected', function (): void {
    $this->withoutMiddleware()
        ->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->get(route('user.meta-ads.index'))
        ->assertOk()
        ->assertSee('Connect your Meta Business to see ad spend')
        ->assertSee('No chats from ads in this period yet.');
});

it('rejects an unknown report period', function (): void {
    $this->withoutMiddleware()
        ->actingAs(User::factory()->create(['email_verified_at' => now()]))
        ->get(route('user.meta-ads.index', ['period' => '1y']))
        ->assertSessionHasErrors('period');
});
