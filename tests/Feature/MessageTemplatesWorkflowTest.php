<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\AiSettings\Models\AiSetting;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MessageTemplates\Models\MessageTemplateSubmission;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function configureMessageTemplatePlatformAi(): void
{
    config([
        'ai.default' => 'openai',
        'ai.providers.openai' => [
            'driver' => 'openai',
            'key' => 'sk-platform-test',
        ],
    ]);
}

function configureMessageTemplateGeminiPlatformAi(): void
{
    Cache::forget('ai_settings_cache');
    AiSetting::query()->updateOrCreate(['key' => 'ai_default_text_provider'], ['value' => 'gemini']);
    AiSetting::query()->updateOrCreate(['key' => 'ai_default_text_model'], ['value' => '']);
    config([
        'ai.default' => 'openai',
        'ai.providers.gemini' => [
            'driver' => 'gemini',
            'key' => 'gemini-platform-test',
        ],
    ]);
}

function messageTemplateChannel(User $user): ChannelAccount
{
    $workspace = app(WorkspaceResolver::class)->current($user);
    $workspace->update(['settings' => array_merge($workspace->settings ?? [], [
        'onboarding_completed_at' => now()->toIso8601String(),
    ])]);
    $plan = Plan::query()->firstOrCreate(
        ['slug' => 'test-plan'],
        ['name' => 'Test Plan', 'price' => 0, 'interval' => 'month', 'limits' => [], 'features' => [], 'is_active' => true]
    );
    Subscription::query()->updateOrCreate(
        ['workspace_id' => $workspace->id],
        ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'renews_at' => now()->addMonth(), 'usage' => []]
    );

    foreach (['templates.manage', 'campaigns.view', 'campaigns.create', 'campaigns.manage'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo(['templates.manage', 'campaigns.view', 'campaigns.create', 'campaigns.manage']);

    return ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'WaPro Coffee',
        'status' => 'connected',
        'credentials' => ['access_token' => 'EAA-test-token'],
        'webhook_verify_token' => 'verify-token',
        'provider_account_id' => '102938475610293',
        'provider_phone_id' => '1069382741050193',
        'provider_display_id' => '+1 503 555 0119',
        'settings' => ['inbox_active' => true],
        'connected_at' => now(),
    ]);
}

function messageTemplateAiCreditPlan(int $workspaceId, int $credits, int $used = 0): Subscription
{
    $plan = Plan::query()->create([
        'name' => 'Template AI Credits',
        'slug' => 'template-ai-credits-'.$workspaceId.'-'.$credits.'-'.$used,
        'price' => 0,
        'interval' => 'month',
        'limits' => ['max_ai_credits' => $credits],
        'features' => [],
        'is_active' => true,
    ]);

    return Subscription::query()->updateOrCreate(
        ['workspace_id' => $workspaceId],
        ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'renews_at' => now()->addMonth(), 'usage' => ['max_ai_credits' => $used]]
    );
}

function telegramTemplateChannel(User $user): ChannelAccount
{
    $workspace = app(WorkspaceResolver::class)->current($user);

    return ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'telegram',
        'name' => 'WaPro Telegram',
        'status' => 'connected',
        'credentials' => ['access_token' => 'telegram-test-token'],
        'webhook_verify_token' => 'telegram-verify-token',
        'provider_account_id' => 'wapro_bot',
        'provider_display_id' => 'WaPro Bot',
        'settings' => [],
        'connected_at' => now(),
    ]);
}

it('registers dynamic message template routes', function (): void {
    expect(Route::has('user.message-templates.index'))->toBeTrue()
        ->and(Route::has('user.message-templates.generate'))->toBeTrue()
        ->and(Route::has('user.message-templates.store'))->toBeTrue()
        ->and(Route::has('user.message-templates.edit'))->toBeTrue()
        ->and(Route::has('user.message-templates.update'))->toBeTrue()
        ->and(Route::has('user.message-templates.destroy'))->toBeTrue()
        ->and(Route::has('user.message-templates.submit'))->toBeTrue()
        ->and(Route::has('user.message-templates.sync'))->toBeTrue();
});

it('renders saved templates instead of static sample data', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $channel = messageTemplateChannel($user);

    MessageTemplate::query()->create([
        'workspace_id' => $channel->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'shipping_update',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [['type' => 'BODY', 'text' => 'Hi {{full_name}}, your order has shipped.']],
    ]);

    $this->actingAs($user)
        ->get(route('user.message-templates.index'))
        ->assertOk()
        ->assertSee('shipping_update')
        ->assertSee('Hi {{full_name}}, your order has shipped.')
        ->assertSee('Total templates')
        ->assertSee('Generate with AI')
        ->assertSee('WhatsApp template guidance')
        ->assertSee(route('user.message-templates.edit', MessageTemplate::query()->first()), false)
        ->assertDontSee('order_ready');
});

it('renders template ai modal instructions for whatsapp and telegram', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    messageTemplateChannel($user);
    telegramTemplateChannel($user);

    $this->actingAs($user)
        ->get(route('user.message-templates.index', ['provider' => 'whatsapp']))
        ->assertOk()
        ->assertSee('Generate with AI')
        ->assertSee('Generate WhatsApp Template')
        ->assertSee('WhatsApp template guidance')
        ->assertSee('csrf-token', false)
        ->assertSee('templates\/generate', false);

    $this->actingAs($user)
        ->get(route('user.message-templates.index', ['provider' => 'telegram']))
        ->assertOk()
        ->assertSee('Generate with AI')
        ->assertSee('Generate Telegram Template')
        ->assertSee('Telegram template guidance')
        ->assertSee('templates\/generate', false);
});

it('creates updates and deletes a user template', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    messageTemplateChannel($user);

    $this->actingAs($user)
        ->post(route('user.message-templates.store'), [
            'name' => 'welcome_offer',
            'provider' => 'whatsapp',
            'language' => 'en_US',
            'category' => 'marketing',
            'body' => 'Hi {{full_name}}, welcome to WaPro.',
            'header' => ['type' => 'text', 'text' => 'Welcome'],
            'footer' => ['text' => 'Reply STOP to opt out'],
            'submit_to_meta' => '0',
        ])
        ->assertRedirect(route('user.message-templates.index', ['provider' => 'whatsapp']));

    $template = MessageTemplate::query()->where('name', 'welcome_offer')->firstOrFail();

    expect($template->status->value)->toBe('draft')
        ->and(data_get($template->components, '0.type'))->toBe('HEADER')
        ->and(data_get($template->components, '0.format'))->toBe('TEXT')
        ->and(data_get($template->components, '0.text'))->toBe('Welcome');
    expect($template->getAttribute('channel_account_id'))->toBeNull();

    $this->actingAs($user)
        ->get(route('user.message-templates.edit', $template))
        ->assertOk()
        ->assertSee('Edit Template')
        ->assertSee('welcome_offer')
        ->assertSee('Hi {{full_name}}, welcome to WaPro.');

    $this->actingAs($user)
        ->put(route('user.message-templates.update', $template), [
            'name' => 'welcome_offer_v2',
            'provider' => 'whatsapp',
            'language' => 'en_US',
            'category' => 'marketing',
            'body' => 'Hi {{full_name}}, here is your new offer.',
            'submit_to_meta' => '0',
        ])
        ->assertRedirect(route('user.message-templates.index', ['provider' => 'whatsapp']));

    expect($template->fresh()->name)->toBe('welcome_offer_v2');

    $this->actingAs($user)
        ->delete(route('user.message-templates.destroy', $template))
        ->assertRedirect();

    expect(MessageTemplate::query()->whereKey($template->id)->exists())->toBeFalse();
});

it('renders a compact centered live preview for whatsapp and telegram templates', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    messageTemplateChannel($user);
    telegramTemplateChannel($user);

    $this->actingAs($user)
        ->get(route('user.message-templates.create', ['provider' => 'whatsapp']))
        ->assertOk()
        ->assertSee('Live WhatsApp Preview')
        ->assertDontSee('generateUrl', false)
        ->assertDontSee('ph-sparkle', false)
        ->assertSee('wa-phone--compact')
        ->assertSee('wa-phone--centered');

    $this->actingAs($user)
        ->get(route('user.message-templates.create', ['provider' => 'telegram']))
        ->assertOk()
        ->assertSee('Live Telegram Preview')
        ->assertDontSee('generateUrl', false)
        ->assertDontSee('ph-sparkle', false)
        ->assertSee('wa-phone--compact')
        ->assertSee('wa-phone--centered');
});

it('generates a whatsapp template draft with platform ai and redirects to edit', function (): void {
    configureMessageTemplatePlatformAi();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $channel = messageTemplateChannel($user);
    $subscription = messageTemplateAiCreditPlan($channel->workspace_id, 5);

    $response = $this->actingAs($user)
        ->postJson(route('user.message-templates.generate'), [
            'provider' => 'whatsapp',
            'prompt' => 'Announce a limited coffee offer',
        ])
        ->assertOk()
        ->assertJsonPath('provider', 'openai')
        ->assertJsonPath('name', 'announce_a_limited_coffee_offer')
        ->assertJsonStructure(['template_id', 'redirect_url', 'name', 'provider', 'model']);

    $template = MessageTemplate::query()->where('name', 'announce_a_limited_coffee_offer')->firstOrFail();

    expect($response->json('redirect_url'))->toBe(route('user.message-templates.edit', $template))
        ->and($template->status->value)->toBe('draft')
        ->and($template->category)->toBe('marketing')
        ->and(data_get($template->components, '0.type'))->toBe('HEADER')
        ->and(data_get($template->components, '1.text'))->toBe('Hi {{full_name}}, Announce a limited coffee offer Tap a button below if you need help.')
        ->and(MessageTemplate::query()->where('name', 'announce_a_limited_coffee_offer')->count())->toBe(1)
        ->and((int) data_get($subscription->fresh()->usage, 'max_ai_credits'))->toBe(1);
});

it('rejects template ai generation when platform ai credits are over', function (): void {
    configureMessageTemplatePlatformAi();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $channel = messageTemplateChannel($user);
    $subscription = messageTemplateAiCreditPlan($channel->workspace_id, 1, 1);

    $this->actingAs($user)
        ->postJson(route('user.message-templates.generate'), [
            'provider' => 'whatsapp',
            'prompt' => 'Draft a reminder',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan')
        ->assertJsonPath('errors.plan.0', 'Your platform AI credits are over. Please upgrade your plan or buy more credits.');

    expect(MessageTemplate::query()->where('workspace_id', $channel->workspace_id)->count())->toBe(0)
        ->and((int) data_get($subscription->fresh()->usage, 'max_ai_credits'))->toBe(1);
});

it('generates a telegram template with platform ai and redirects to edit', function (): void {
    configureMessageTemplatePlatformAi();
    $user = User::factory()->create(['email_verified_at' => now()]);
    messageTemplateChannel($user);
    telegramTemplateChannel($user);

    $this->actingAs($user)
        ->postJson(route('user.message-templates.generate'), [
            'provider' => 'telegram',
            'prompt' => 'Invite subscribers to confirm their booking',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'invite_subscribers_to_confirm_th')
        ->assertJsonStructure(['template_id', 'redirect_url', 'name', 'provider', 'model']);

    $template = MessageTemplate::query()->where('name', 'invite_subscribers_to_confirm_th')->firstOrFail();

    expect($template->provider)->toBe('telegram')
        ->and($template->status->value)->toBe('approved')
        ->and($template->category)->toBe('utility')
        ->and(data_get($template->components, '0.text'))->toBe('Hi {{full_name}}, Invite subscribers to confirm their booking Reply when you are ready.')
        ->and(data_get($template->components, '1.buttons.0.type'))->toBe('CALLBACK');
});

it('uses gemini platform ai settings with a fallback text model for template generation', function (): void {
    configureMessageTemplateGeminiPlatformAi();
    $user = User::factory()->create(['email_verified_at' => now()]);
    messageTemplateChannel($user);

    $this->actingAs($user)
        ->postJson(route('user.message-templates.generate'), [
            'provider' => 'whatsapp',
            'prompt' => 'Share a shipping delay update',
        ])
        ->assertOk()
        ->assertJsonPath('provider', 'gemini')
        ->assertJsonPath('model', 'gemini-3-flash-preview')
        ->assertJsonStructure(['template_id', 'redirect_url']);

    expect(MessageTemplate::query()->where('name', 'share_a_shipping_delay_update')->exists())->toBeTrue();
});

it('rejects template ai generation when platform ai is not configured', function (): void {
    config([
        'ai.default' => 'openai',
        'ai.providers.openai' => [
            'driver' => 'openai',
        ],
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    messageTemplateChannel($user);

    $this->actingAs($user)
        ->postJson(route('user.message-templates.generate'), [
            'provider' => 'whatsapp',
            'prompt' => 'Draft a reminder',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai');
});

it('requires templates manage permission before generating message templates', function (): void {
    configureMessageTemplatePlatformAi();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($owner);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $workspace->members()->attach($member->id, [
        'role' => WorkspaceMemberRole::Staff->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($member)
        ->postJson(route('user.message-templates.generate'), [
            'provider' => 'whatsapp',
            'prompt' => 'Draft a reminder',
        ])
        ->assertForbidden();
});

it('keeps telegram templates separate from whatsapp templates', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $whatsapp = messageTemplateChannel($user);
    telegramTemplateChannel($user);

    MessageTemplate::query()->create([
        'workspace_id' => $whatsapp->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'shared_name',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [['type' => 'BODY', 'text' => 'WhatsApp only {{full_name}}']],
    ]);

    $this->actingAs($user)
        ->post(route('user.message-templates.store'), [
            'name' => 'shared_name',
            'provider' => 'telegram',
            'language' => 'en_US',
            'body' => 'Telegram only {{full_name}}',
            'buttons' => [
                ['type' => 'url', 'text' => 'Open site', 'url' => 'https://example.com/users/{{custom.slug}}'],
                ['type' => 'callback', 'text' => 'Start', 'callback_data' => 'start_{{phone}}'],
            ],
            'submit_to_meta' => '0',
        ])
        ->assertRedirect(route('user.message-templates.index', ['provider' => 'telegram']));

    $telegramTemplate = MessageTemplate::query()
        ->where('provider', 'telegram')
        ->where('name', 'shared_name')
        ->firstOrFail();

    expect($telegramTemplate->status->value)->toBe('approved')
        ->and(data_get($telegramTemplate->components, '0.text'))->toBe('Telegram only {{full_name}}')
        ->and(data_get($telegramTemplate->components, '1.buttons.1.callback_data'))->toBe('start_{{phone}}')
        ->and(MessageTemplate::query()->where('name', 'shared_name')->count())->toBe(2);

    $this->actingAs($user)
        ->get(route('user.message-templates.index', ['provider' => 'telegram']))
        ->assertOk()
        ->assertSee('Telegram only {{full_name}}')
        ->assertDontSee('WhatsApp only {{full_name}}');

    $this->actingAs($user)
        ->get(route('user.message-templates.index', ['provider' => 'whatsapp']))
        ->assertOk()
        ->assertSee('WhatsApp only {{full_name}}')
        ->assertDontSee('Telegram only {{full_name}}');
});

it('submits a saved template to Meta Cloud API', function (): void {
    app(WhatsAppSettingsService::class)->update(['whatsapp_graph_api_version' => 'v20.0']);

    Http::fake([
        'https://graph.facebook.com/v20.0/102938475610293/message_templates' => Http::response([
            'id' => 'meta-template-123',
        ]),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $channel = messageTemplateChannel($user);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $channel->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'promo_alert',
        'language' => 'en_US',
        'category' => 'marketing',
        'status' => 'draft',
        'components' => [['type' => 'BODY', 'text' => 'Hello {{full_name}}, sale is live.']],
        'submission_payload' => [
            'name' => 'promo_alert',
            'language' => 'en_US',
            'category' => 'MARKETING',
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, sale is live.']],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('user.message-templates.submit', $template))
        ->assertRedirect();

    $template->refresh();
    $submission = MessageTemplateSubmission::query()->where('message_template_id', $template->id)->firstOrFail();

    expect($submission->whatsapp_template_id)->toBe('meta-template-123')
        ->and($submission->provider_account_id)->toBe('102938475610293')
        ->and($submission->status->value)->toBe('submitted')
        ->and($submission->submitted_at)->not->toBeNull()
        ->and($template->status->value)->toBe('pending')
        ->and(data_get($template->variables, 'whatsapp.body.full_name'))->toBe('1')
        ->and(data_get($template->submission_payload, 'components.0.text'))->toBe('Hello {{1}}, sale is live.');
});

it('shows the Meta rejection reason for failed template submissions', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $channel = messageTemplateChannel($user);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $channel->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'bad_params',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'failed',
        'components' => [['type' => 'BODY', 'text' => 'Hello {{1}} {{2}}']],
        'submission_payload' => [
            'name' => 'bad_params',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}} {{2}}']],
        ],
    ]);

    MessageTemplateSubmission::query()->create([
        'workspace_id' => $channel->workspace_id,
        'message_template_id' => $template->id,
        'channel_account_id' => $channel->id,
        'provider' => 'whatsapp',
        'provider_account_id' => $channel->provider_account_id,
        'status' => 'failed',
        'submission_payload' => $template->submission_payload,
        'meta_response' => [
            'error' => [
                'error_user_title' => 'Leading or Trailing Params Not Allowed',
                'error_user_msg' => 'Variables cannot be at the start or end of the template.',
            ],
        ],
        'submitted_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('user.message-templates.index'))
        ->assertOk()
        ->assertSee('Leading or Trailing Params Not Allowed')
        ->assertSee('Variables cannot be at the start or end of the template.');
});

it('blocks templates whose body starts or ends with a variable before Meta submit', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    messageTemplateChannel($user);

    $this->actingAs($user)
        ->from(route('user.message-templates.create'))
        ->post(route('user.message-templates.store'), [
            'name' => 'bad_variable_position',
            'provider' => 'whatsapp',
            'language' => 'en_US',
            'category' => 'utility',
            'body' => 'Hello {{full_name}}, your code is {{custom.code}}',
            'submit_to_meta' => '1',
        ])
        ->assertRedirect(route('user.message-templates.create'))
        ->assertSessionHasErrors('body');

    expect(MessageTemplate::query()->where('name', 'bad_variable_position')->exists())->toBeFalse();
});

it('syncs templates from Meta Cloud API into the user workspace', function (): void {
    app(WhatsAppSettingsService::class)->update(['whatsapp_graph_api_version' => 'v20.0']);

    Http::fake([
        'https://graph.facebook.com/v20.0/102938475610293/message_templates' => Http::response([
            'data' => [[
                'id' => 'meta-template-999',
                'name' => 'pickup_ready',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => 'APPROVED',
                'components' => [['type' => 'BODY', 'text' => 'Your order is ready.']],
            ]],
        ]),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    messageTemplateChannel($user);

    $this->actingAs($user)
        ->post(route('user.message-templates.sync'))
        ->assertRedirect()
        ->assertSessionHas('status');

    $template = MessageTemplate::query()->where('name', 'pickup_ready')->firstOrFail();
    $submission = MessageTemplateSubmission::query()->where('message_template_id', $template->id)->firstOrFail();

    expect($submission->whatsapp_template_id)->toBe('meta-template-999')
        ->and($submission->provider_account_id)->toBe('102938475610293')
        ->and($template->status->value)->toBe('approved')
        ->and($submission->synced_at)->not->toBeNull();
});

it('requires WABA selection when submitting with multiple WhatsApp Business Accounts', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $first = messageTemplateChannel($user);
    ChannelAccount::query()->create([
        'workspace_id' => $first->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'WaPro Wholesale',
        'status' => 'connected',
        'credentials' => ['access_token' => 'EAA-second-token'],
        'webhook_verify_token' => 'verify-token-2',
        'provider_account_id' => '998877665544332',
        'provider_phone_id' => '222222222222222',
        'provider_display_id' => '+1 503 555 0120',
        'settings' => [],
        'connected_at' => now(),
    ]);

    $template = MessageTemplate::query()->create([
        'workspace_id' => $first->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'promo_alert',
        'language' => 'en_US',
        'category' => 'marketing',
        'status' => 'draft',
        'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, sale is live.']],
        'submission_payload' => [
            'name' => 'promo_alert',
            'language' => 'en_US',
            'category' => 'MARKETING',
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, sale is live.']],
        ],
    ]);

    $this->actingAs($user)
        ->from(route('user.message-templates.index'))
        ->post(route('user.message-templates.submit', $template))
        ->assertRedirect(route('user.message-templates.index'))
        ->assertSessionHasErrors('provider_account_id');
});

it('deduplicates template sync by WABA when multiple phone numbers share the same WABA', function (): void {
    app(WhatsAppSettingsService::class)->update(['whatsapp_graph_api_version' => 'v20.0']);

    Http::fake([
        'https://graph.facebook.com/v20.0/102938475610293/message_templates' => Http::response([
            'data' => [[
                'id' => 'meta-template-999',
                'name' => 'pickup_ready',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => 'APPROVED',
                'components' => [['type' => 'BODY', 'text' => 'Your order is ready.']],
            ]],
        ]),
    ]);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $channel = messageTemplateChannel($user);
    ChannelAccount::query()->create([
        'workspace_id' => $channel->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'WaPro Second Number',
        'status' => 'connected',
        'credentials' => ['access_token' => 'EAA-second-token'],
        'webhook_verify_token' => 'verify-token-2',
        'provider_account_id' => '102938475610293',
        'provider_phone_id' => '2069382741050193',
        'provider_display_id' => '+1 503 555 0120',
        'settings' => [],
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('user.message-templates.sync'))
        ->assertRedirect()
        ->assertSessionHas('status');

    $template = MessageTemplate::query()->where('name', 'pickup_ready')->firstOrFail();

    expect(MessageTemplateSubmission::query()->where('message_template_id', $template->id)->count())->toBe(1);
});

it('blocks campaign creation when the template is not approved for the selected WABA', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $approvedChannel = messageTemplateChannel($user);
    $otherChannel = ChannelAccount::query()->create([
        'workspace_id' => $approvedChannel->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'WaPro Wholesale',
        'status' => 'connected',
        'credentials' => ['access_token' => 'EAA-second-token'],
        'webhook_verify_token' => 'verify-token-2',
        'provider_account_id' => '998877665544332',
        'provider_phone_id' => '222222222222222',
        'provider_display_id' => '+1 503 555 0120',
        'settings' => [],
        'connected_at' => now(),
    ]);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $approvedChannel->workspace_id,
        'provider' => 'whatsapp',
        'name' => 'pickup_ready',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => 'approved',
        'components' => [['type' => 'BODY', 'text' => 'Your order is ready.']],
        'submission_payload' => [
            'name' => 'pickup_ready',
            'language' => 'en_US',
            'category' => 'UTILITY',
            'components' => [['type' => 'BODY', 'text' => 'Your order is ready.']],
        ],
    ]);
    MessageTemplateSubmission::query()->create([
        'workspace_id' => $approvedChannel->workspace_id,
        'message_template_id' => $template->id,
        'channel_account_id' => $approvedChannel->id,
        'provider' => 'whatsapp',
        'provider_account_id' => $approvedChannel->provider_account_id,
        'whatsapp_template_id' => 'meta-template-999',
        'status' => 'approved',
        'submission_payload' => $template->submission_payload,
        'meta_response' => ['id' => 'meta-template-999'],
        'synced_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('user.campaigns.create'))
        ->post(route('user.campaigns.store'), [
            'name' => 'Wrong WABA campaign',
            'provider' => 'whatsapp',
            'channel_account_id' => $otherChannel->id,
            'message_type' => 'template',
            'message_template_id' => $template->id,
            'audience_type' => 'groups',
            'schedule' => 'now',
        ])
        ->assertRedirect(route('user.campaigns.create'))
        ->assertSessionHasErrors('message_template_id');
});
