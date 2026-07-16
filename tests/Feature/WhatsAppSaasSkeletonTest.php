<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\Admin;
use App\Models\User;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotAiProvider;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\Settings\Models\Setting;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('registers the WhatsApp SaaS tenant routes', function (): void {
    foreach ([
        'user.whatsapp-cloud.channel-setup',
        'user.whatsapp-cloud.channel-setup.embedded',
        'user.inbox.index',
        'user.contacts.index',
        'user.segments.index',
        'user.leads.index',
        'user.message-templates.index',
        'user.campaigns.index',
        'user.auto-replies.index',
        'user.automations.index',
        'user.chatbots.index',
        'user.knowledge-bases.index',
        'user.subscription.show',
        'user.workspaces.team',
    ] as $route) {
        expect(Route::has($route))->toBeTrue($route.' should be registered');
    }
});

it('serves the core WhatsApp SaaS user pages', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertOk()
        ->assertSee("Here's how your WhatsApp workspace is performing.")
        ->assertSee('Messages sent')
        ->assertSee('0 / 10k');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('Connect and manage provider channels')
        ->assertSee('No channel connected yet')
        ->assertSee('Choose a channel')
        ->assertSee('WhatsApp')
        ->assertSee('Facebook')
        ->assertSee('Threads')
        ->assertSee('Instagram')
        ->assertSee('Telegram')
        ->assertSee('Business ID')
        ->assertSee('Webhook Verify Token')
        ->assertSee('Connect with Meta')
        ->assertDontSee('Recommended')
        ->assertSee('Admin setup required')
        ->assertSee('class="card mt-6 overflow-hidden p-0"', false)
        ->assertSee('class="mt-5 rounded-xl border border-neutral-100 bg-section p-4"', false)
        ->assertSee('/webhooks/channels/whatsapp');
});

it('shows embedded signup as the recommended channel setup path when admin settings are complete', function (): void {
    app(WhatsAppSettingsService::class)->update([
        'whatsapp_embedded_signup_enabled' => true,
        'whatsapp_meta_app_id' => '123456789',
        'whatsapp_meta_app_secret' => 'secret',
        'whatsapp_embedded_signup_config_id' => 'config-123',
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('Manual setup')
        ->assertSee('Connect with Meta')
        ->assertSee('Recommended')
        ->assertDontSee('Admin setup required')
        ->assertSee('data-app-id="123456789"', false)
        ->assertSee('data-config-id="config-123"', false)
        ->assertSee(route('user.whatsapp-cloud.channel-setup.embedded'));
});

it('updates WhatsApp Cloud admin settings without an unused default rate limit', function (): void {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.whatsapp-cloud.settings.index'))
        ->assertOk()
        ->assertSee('WhatsApp Cloud Settings')
        ->assertDontSee('Default Rate Limit / Minute')
        ->assertDontSee('whatsapp_default_rate_limit_per_minute');

    $this->actingAs($admin, 'admin')
        ->put(route('admin.whatsapp-cloud.settings.update'), [
            'settings' => [
                'whatsapp_graph_api_version' => 'v21.0',
                'whatsapp_meta_app_id' => '123456789',
                'whatsapp_meta_app_secret' => 'secret',
                'whatsapp_embedded_signup_config_id' => 'config-123',
                'whatsapp_default_verify_token' => 'verify-token',
                'whatsapp_webhook_base_url' => 'https://example.com',
                'whatsapp_auto_sync_templates' => '1',
                'whatsapp_auto_sync_phone_numbers' => '1',
                'whatsapp_embedded_signup_enabled' => '1',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(app(WhatsAppSettingsService::class)->get('whatsapp_graph_api_version'))->toBe('v21.0')
        ->and(Setting::query()->where('key', 'whatsapp_default_rate_limit_per_minute')->exists())->toBeFalse();
});

it('connects a WhatsApp channel manually', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Http::fake([
        'https://graph.facebook.com/v20.0/102938475610293/phone_numbers*' => Http::response([
            'data' => [[
                'id' => '1069382741050193',
                'display_phone_number' => '+1 503 555 0119',
                'verified_name' => 'WaPro Coffee Co.',
            ]],
        ]),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.store'), [
            'name' => 'WaPro Coffee Co.',
            'waba_id' => '102938475610293',
            'business_id' => '784512309876543',
            'phone_number_id' => '1069382741050193',
            'access_token' => 'EAA-manual-token',
            'webhook_verify_token' => 'verify-manual-token',
        ])
        ->assertRedirect();

    $channel = ChannelAccount::query()->where('provider_phone_id', '1069382741050193')->firstOrFail();
    $rawCredentials = DB::table('channel_accounts')->where('id', $channel->id)->value('credentials');

    expect($channel->credential('access_token'))->toBe('EAA-manual-token')
        ->and($rawCredentials)->not->toContain('EAA-manual-token')
        ->and($channel->provider_account_id)->toBe('102938475610293')
        ->and($channel->provider_display_id)->toBe('+1 503 555 0119')
        ->and(data_get($channel->settings, 'business_id'))->toBe('784512309876543')
        ->and(data_get($channel->settings, 'verified_name'))->toBe('WaPro Coffee Co.')
        ->and($channel->webhook_verify_token)->toBe('verify-manual-token');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('verify-manual-token')
        ->assertSee(route('webhooks.channels.receive', 'whatsapp'))
        ->assertDontSee('/webhooks/channels/'.$channel->id)
        ->assertDontSee('EAA-manual-token');

    $this->get(route('webhooks.channels.verify', [
        'provider' => 'whatsapp',
        'hub.mode' => 'subscribe',
        'hub.verify_token' => 'verify-manual-token',
        'hub.challenge' => 'challenge-token',
    ]))
        ->assertOk()
        ->assertSee('challenge-token');
});

it('connects one manual channel per provider and updates it on reconnect', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Http::fake([
        'https://api.telegram.org/botbot-token-one/getMe' => Http::response([
            'ok' => true,
            'result' => ['username' => 'support_bot'],
        ], 200),
        'https://api.telegram.org/botbot-token-one/setWebhook' => Http::response([
            'ok' => true,
            'description' => 'Webhook was set',
        ], 200),
        'https://api.telegram.org/botbot-token-two/getMe' => Http::response([
            'ok' => true,
            'result' => ['username' => 'updated_bot'],
        ], 200),
        'https://api.telegram.org/botbot-token-two/setWebhook' => Http::response([
            'ok' => true,
            'description' => 'Webhook was set',
        ], 200),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.store-generic'), [
            'provider' => 'telegram',
            'name' => 'Support Bot',
            'provider_account_id' => 'support_bot',
            'provider_display_id' => 'Support',
            'access_token' => 'bot-token-one',
            'webhook_verify_token' => 'telegram-verify-one',
        ])
        ->assertRedirect();

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.store-generic'), [
            'provider' => 'telegram',
            'name' => 'Updated Bot',
            'provider_account_id' => 'updated_bot',
            'provider_display_id' => 'Updated Support',
            'access_token' => 'bot-token-two',
            'webhook_verify_token' => 'telegram-verify-two',
        ])
        ->assertRedirect();

    expect(ChannelAccount::query()->where('provider', 'telegram')->count())->toBe(1);

    $channel = ChannelAccount::query()->where('provider', 'telegram')->firstOrFail();

    expect($channel->name)->toBe('Updated Bot')
        ->and($channel->provider_account_id)->toBe('updated_bot')
        ->and($channel->credential('access_token'))->toBe('bot-token-two')
        ->and($channel->webhook_verify_token)->toBe('telegram-verify-two')
        ->and($channel->settings)->toHaveKey('last_webhook_set_at')
        ->and($channel->settings['telegram_bot_username'])->toBe('updated_bot');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/botbot-token-two/setWebhook'
        && $request['secret_token'] === 'telegram-verify-two'
        && str_ends_with((string) $request['url'], '/webhooks/channels/telegram/'.$channel->webhook_code));

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('Updated Bot')
        ->assertSee(route('webhooks.channels.receive', 'telegram'))
        ->assertDontSee('bot-token-two');
});

it('connects non-webhook channels without exposing webhook setup', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.store-generic'), [
            'provider' => 'email',
            'name' => 'Primary Email',
            'provider_display_id' => 'noreply@example.com',
            'mail_mailer' => 'log',
            'webhook_verify_token' => 'email-verify-should-not-store',
        ])
        ->assertRedirect();

    $channel = ChannelAccount::query()->where('provider', 'email')->firstOrFail();

    expect($channel->name)->toBe('Primary Email')
        ->and($channel->webhook_verify_token)->toBeNull();

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('Primary Email')
        ->assertDontSee('email-verify-should-not-store')
        ->assertDontSee('/webhooks/channels/email');
});

it('shows internal widget channels without exposing connect or edit drawer actions', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'website_widget',
        'name' => 'Homepage Widget',
        'status' => 'connected',
        'provider_account_id' => 'widget-homepage',
        'provider_display_id' => 'Website visitor',
        'connected_at' => now(),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('Homepage Widget')
        ->assertSee('Website Chat Widget')
        ->assertDontSee('data-drawer-provider="website_widget"', false)
        ->assertDontSee('Update Website widget')
        ->assertDontSee('Connect Website widget');
});

it('does not allow internal widget channels to be connected manually', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.whatsapp-cloud.channel-setup'))
        ->post(route('user.whatsapp-cloud.channel-setup.store-generic'), [
            'provider' => 'website_widget',
            'name' => 'Manual Widget',
            'provider_account_id' => 'manual-widget',
        ])
        ->assertRedirect(route('user.whatsapp-cloud.channel-setup'))
        ->assertSessionHasErrors('provider');
});

it('verifies provider webhooks by provider name and verify token', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);

    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'telegram',
        'name' => 'Support Bot',
        'status' => 'connected',
        'webhook_verify_token' => 'telegram-secret',
        'provider_account_id' => 'support_bot',
        'connected_at' => now(),
    ]);

    $this->get(route('webhooks.channels.verify', [
        'provider' => 'telegram',
        'hub.mode' => 'subscribe',
        'hub.verify_token' => 'telegram-secret',
        'hub.challenge' => 'telegram-challenge',
    ]))
        ->assertOk()
        ->assertSee('telegram-challenge');
});

it('saves manual WhatsApp credentials with an error when Meta validation fails', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Http::fake([
        'https://graph.facebook.com/v20.0/102938475610293/phone_numbers*' => Http::response([
            'error' => ['message' => 'Invalid OAuth access token.'],
        ], 401),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.whatsapp-cloud.channel-setup'))
        ->post(route('user.whatsapp-cloud.channel-setup.store'), [
            'name' => 'WaPro Coffee Co.',
            'waba_id' => '102938475610293',
            'business_id' => '784512309876543',
            'phone_number_id' => '1069382741050193',
            'access_token' => 'bad-token',
            'webhook_verify_token' => 'verify-manual-token',
        ])
        ->assertRedirect(route('user.whatsapp-cloud.channel-setup'))
        ->assertSessionHas('status');

    $channel = ChannelAccount::query()->where('provider_phone_id', '1069382741050193')->firstOrFail();

    expect($channel->status->value)->toBe('error')
        ->and(data_get($channel->settings, 'last_error'))->toBe('Meta rejected these credentials. Check the WABA ID and access token.')
        ->and($channel->credential('access_token'))->toBe('bad-token');

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.whatsapp-cloud.channel-setup'))
        ->assertOk()
        ->assertSee('Meta rejected these credentials. Check the WABA ID and access token.')
        ->assertDontSee('bad-token');
});

it('connects a WhatsApp channel from embedded signup', function (): void {
    app(WhatsAppSettingsService::class)->update([
        'whatsapp_graph_api_version' => 'v20.0',
        'whatsapp_embedded_signup_enabled' => true,
        'whatsapp_meta_app_id' => '123456789',
        'whatsapp_meta_app_secret' => 'secret',
        'whatsapp_embedded_signup_config_id' => 'config-123',
        'whatsapp_auto_sync_templates' => true,
        'whatsapp_auto_sync_phone_numbers' => true,
    ]);

    Http::fake([
        'https://graph.facebook.com/v20.0/oauth/access_token*' => Http::response([
            'access_token' => 'EAA-embedded-token',
            'token_type' => 'bearer',
        ]),
        'https://graph.facebook.com/v20.0/102938475610293/phone_numbers*' => Http::response([
            'data' => [[
                'id' => '1069382741050193',
                'display_phone_number' => '+1 503 555 0119',
                'verified_name' => 'WaPro Coffee Co.',
                'quality_rating' => 'GREEN',
                'code_verification_status' => 'VERIFIED',
            ]],
        ]),
        'https://graph.facebook.com/v20.0/102938475610293/message_templates*' => Http::response([
            'data' => [],
        ]),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.embedded'), [
            'code' => 'embedded-code',
            'waba_id' => '102938475610293',
            'phone_number_id' => '1069382741050193',
            'business_id' => 'business-123',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $channel = ChannelAccount::query()->where('provider_phone_id', '1069382741050193')->firstOrFail();

    expect($channel->credential('access_token'))->toBe('EAA-embedded-token')
        ->and($channel->credential('source'))->toBe('embedded_signup')
        ->and($channel->provider_account_id)->toBe('102938475610293')
        ->and($channel->provider_display_id)->toBe('+1 503 555 0119');
});

it('does not save embedded signup credentials when meta code exchange fails', function (): void {
    app(WhatsAppSettingsService::class)->update([
        'whatsapp_embedded_signup_enabled' => true,
        'whatsapp_meta_app_id' => '123456789',
        'whatsapp_meta_app_secret' => 'secret',
        'whatsapp_embedded_signup_config_id' => 'config-123',
    ]);

    Http::fake([
        'https://graph.facebook.com/v20.0/oauth/access_token*' => Http::response(['error' => ['message' => 'Invalid code']], 400),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.whatsapp-cloud.channel-setup'))
        ->post(route('user.whatsapp-cloud.channel-setup.embedded'), [
            'code' => 'bad-code',
            'waba_id' => '102938475610293',
            'phone_number_id' => '1069382741050193',
        ])
        ->assertRedirect(route('user.whatsapp-cloud.channel-setup'))
        ->assertSessionHasErrors('embedded_signup');

    expect(ChannelAccount::query()->where('provider_phone_id', '1069382741050193')->exists())->toBeFalse();
});

it('serves converted WhatsApp HTML user app pages', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Gate::before(fn (): bool => true);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $aiProvider = ChatbotAiProvider::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'openai',
        'name' => 'Workspace OpenAI',
        'credentials' => ['api_key' => 'sk-smoke'],
        'settings' => ['default_model' => 'gpt-4o'],
        'is_active' => true,
    ]);
    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_ai_provider_id' => $aiProvider->id,
        'name' => 'Support Assistant',
        'persona' => 'Answer customer support questions.',
        'greeting' => 'How can I help?',
        'model' => 'gpt-4o',
        'is_active' => true,
    ]);
    $campaign = Campaign::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'email',
        'uuid' => (string) Str::uuid(),
        'name' => 'Smoke Campaign',
        'status' => CampaignStatus::Completed->value,
        'audience_type' => 'groups',
        'send_rate_per_minute' => 60,
    ]);
    DB::table('campaigns')->where('id', $campaign->id)->update(['status' => CampaignStatus::Completed->value]);
    $middleware = [Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class];

    $pages = [
        'user.campaigns.report' => 'Campaign Report',
        'user.inbox.index' => 'All chats',
        'user.contacts.index' => 'Drop your file here or click to browse',
        'user.groups.index' => 'Contact Groups',
        'user.leads.index' => 'Leads',
        'user.message-templates.index' => 'Template Library',
        'user.message-templates.create' => 'New Template',
        'user.campaigns.index' => 'Campaigns',
        'user.campaigns.create' => 'New Campaign',
        'user.auto-replies.index' => 'Auto Replies',
        'user.auto-replies.create' => 'New Auto-Reply Rule',
        'user.automations.index' => 'Automations',
        'user.automations.builder' => 'Flow Builder',
        'user.chatbots.index' => 'AI Chatbots',
        'user.chatbots.config' => 'Support Assistant',
        'user.knowledge-bases.index' => 'Knowledge Bases',
        'user.subscription.show' => 'Subscription',
        'user.workspaces.team' => 'Team',
        'user.workspaces.index' => 'Workspaces',
    ];

    foreach ($pages as $route => $text) {
        $parameters = match ($route) {
            'user.campaigns.report' => ['campaign' => $campaign->id],
            'user.chatbots.config' => ['chatbot' => $chatbot->id],
            default => [],
        };

        $response = $this->withoutMiddleware($middleware)->actingAs($user)->get(route($route, $parameters));

        expect($response->getStatusCode(), $route)->toBe(200);
        $response->assertSee($text);
    }
});

it('accepts WhatsApp Cloud API webhook verification and delivery payloads', function (): void {
    Queue::fake();

    $this->get('/webhooks/whatsapp/demo?hub.mode=subscribe&hub.verify_token=abc&hub.challenge=challenge-token')
        ->assertOk()
        ->assertSee('challenge-token');

    $this->postJson('/webhooks/whatsapp/demo', [
        'object' => 'whatsapp_business_account',
        'entry' => [],
    ])->assertOk();
});

it('deletes existing whatsapp templates when updating to a new account', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);

    // 1. Create an existing whatsapp channel account for the workspace
    ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'Old WhatsApp Account',
        'status' => 'connected',
        'provider_account_id' => 'old-waba-id',
        'provider_phone_id' => 'old-phone-id',
        'credentials' => ['access_token' => 'old-token'],
        'webhook_verify_token' => 'verify-old-token',
        'connected_at' => now(),
    ]);

    // 2. Create some existing templates
    MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'hello_world',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
    ]);

    // Verify template exists
    expect(MessageTemplate::query()->where('workspace_id', $workspace->id)->count())->toBe(1);

    // 3. Connect a new WhatsApp account via manual setup route
    Http::fake([
        'https://graph.facebook.com/v20.0/new-waba-id/phone_numbers*' => Http::response([
            'data' => [[
                'id' => 'new-phone-id',
                'display_phone_number' => '+1 503 555 0220',
                'verified_name' => 'New Coffee Co.',
            ]],
        ]),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.whatsapp-cloud.channel-setup.store'), [
            'name' => 'New Coffee Co.',
            'waba_id' => 'new-waba-id',
            'business_id' => '784512309876544',
            'phone_number_id' => 'new-phone-id',
            'access_token' => 'EAA-new-token',
            'webhook_verify_token' => 'verify-new-token',
        ])
        ->assertRedirect();

    // 4. Assert that templates were deleted because the account changed
    expect(MessageTemplate::query()->where('workspace_id', $workspace->id)->count())->toBe(0);
});
