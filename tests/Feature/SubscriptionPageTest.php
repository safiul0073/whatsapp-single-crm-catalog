<?php

use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Currencies\Models\Currency;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\Jobs\ProcessWebhook;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PaymentGateways\Models\WebhookLog;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use App\Modules\PaymentGateways\Services\PaymentService;
use App\Modules\PaymentGatewaySettings\Services\PaymentGatewaySettingsService;
use App\Modules\PlansSubscriptions\Database\Seeders\PlanSeeder;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Jobs\ExpireSubscriptionsJob;
use App\Modules\PlansSubscriptions\Jobs\SendSubscriptionExpiryReminderJob;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('persists the campaign doctor plan feature from the admin form', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('admin.plans.store'), [
            'name' => 'Doctor Growth',
            'slug' => 'doctor-growth',
            'description' => 'Plan with campaign doctor',
            'price' => 49,
            'interval' => 'month',
            'messages_per_month' => 25000,
            'contacts' => 1000,
            'campaign_ai_doctor' => '1',
            'automation_ai_builder' => '1',
            'features_text' => "AI Campaign Doctor\nAI automation builder",
            'is_active' => '1',
            'sort_order' => 15,
        ])
        ->assertRedirect(route('admin.plans.index'));

    $plan = Plan::query()->where('slug', 'doctor-growth')->firstOrFail();

    expect($plan->limits['campaign_ai_doctor'])->toBeTrue()
        ->and($plan->limits['automation_ai_builder'])->toBeTrue()
        ->and($plan->features)->toContain('AI Campaign Doctor');
});

it('seeds campaign doctor as a premium plan feature only', function (): void {
    $this->seed(PlanSeeder::class);

    expect((bool) data_get(Plan::query()->where('slug', 'free')->firstOrFail()->limits, 'campaign_ai_doctor'))->toBeFalse()
        ->and((bool) data_get(Plan::query()->where('slug', 'growth-monthly')->firstOrFail()->limits, 'campaign_ai_doctor'))->toBeTrue()
        ->and((bool) data_get(Plan::query()->where('slug', 'growth-yearly')->firstOrFail()->limits, 'campaign_ai_doctor'))->toBeTrue()
        ->and((bool) data_get(Plan::query()->where('slug', 'lifetime')->firstOrFail()->limits, 'campaign_ai_doctor'))->toBeTrue()
        ->and(Plan::query()->where('slug', 'growth-monthly')->firstOrFail()->features)->toContain('AI Campaign Doctor');
});

it('renders the subscription dashboard from plan and subscription data', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $currencySymbol = html_entity_decode('&#2547;', ENT_QUOTES | ENT_HTML5, 'UTF-8');

    Currency::query()->create([
        'code' => 'BDT',
        'name' => 'Bangladeshi Taka',
        'symbol' => $currencySymbol,
        'exchange_rate' => 1,
        'is_active' => true,
    ]);
    app(PaymentGatewaySettingsService::class)->set('payment_currency', 'BDT');

    $currentPlan = Plan::query()->create([
        'name' => 'Builder Premium',
        'slug' => 'builder-premium',
        'description' => 'Premium builder plan',
        'price' => 49,
        'interval' => 'month',
        'limits' => [
            'messages_per_month' => 25000,
            'team_members' => 5,
            'max_ai_credits' => 50,
            'automation_ai_builder' => true,
        ],
        'features' => [
            'AI automation builder',
            'Priority WhatsApp support',
        ],
        'is_active' => true,
        'sort_order' => 10,
    ]);

    Plan::query()->create([
        'name' => 'Scale Dynamic',
        'slug' => 'scale-dynamic',
        'description' => 'Scale plan',
        'price' => 149,
        'interval' => 'year',
        'limits' => [
            'messages_per_month' => 150000,
            'team_members' => 25,
        ],
        'features' => ['Advanced reports'],
        'is_active' => true,
        'sort_order' => 20,
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $currentPlan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now()->subDay(),
        'renews_at' => now()->addMonth(),
        'usage' => [
            'messages_per_month' => 12500,
            'team_members' => 2,
            'max_ai_credits' => 3,
        ],
    ]);

    $mockManager = Mockery::mock(PaymentGatewayManager::class)->makePartial();
    $mockManager->shouldReceive('getEnabledGatewayNames')->andReturn(['stripe', 'paypal']);
    $this->app->instance(PaymentGatewayManager::class, $mockManager);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.subscription.show'))
        ->assertOk()
        ->assertViewHas('plans', fn ($plans): bool => $plans->count() === 2)
        ->assertViewHas('enabledPaymentGateways', fn ($gateways): bool => $gateways->pluck('label')->all() === ['Stripe', 'PayPal'])
        ->assertSee('Builder Premium')
        ->assertSee('Scale Dynamic')
        ->assertSee($currencySymbol.'49.00')
        ->assertSee($currencySymbol.'149.00')
        ->assertSee('12,500 / 25,000')
        ->assertSee('2 / 5')
        ->assertSee('Platform AI credits')
        ->assertSee('3 / 50')
        ->assertSee('AI automation builder')
        ->assertSee('Payment gateways')
        ->assertSee('Stripe')
        ->assertSee('PayPal')
        ->assertSee('No billing history yet')
        ->assertSee('No payment method on file')
        ->assertDontSee('$49.00')
        ->assertDontSee('$149.00')
        ->assertDontSee('Growth plan')
        ->assertDontSee('Visa');
});

it('initiates subscription checkout correctly', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $plan = Plan::query()->create([
        'name' => 'Premium Upgraded',
        'slug' => 'premium-upgraded',
        'price' => 99,
        'interval' => 'month',
        'is_active' => true,
    ]);

    $workspace->update([
        'settings' => ['onboarding_completed_at' => now()->toIso8601String()],
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
    ]);

    $response = $this->withoutMiddleware([Authorize::class])
        ->actingAs($user)
        ->post(route('user.subscription.checkout.initiate'), [
            'plan_id' => $plan->id,
        ]);

    $payment = Payment::latest()->first();

    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toEqual(99.0)
        ->and((int) data_get($payment->metadata, 'plan_id'))->toBe($plan->id);

    $response->assertRedirect(route('user.subscription.checkout.page', $payment));
});

it('shows completed workspace payments in billing history', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    $otherWorkspace = app(WorkspaceResolver::class)->current($otherUser);

    $plan = Plan::query()->create([
        'name' => 'Stripe Visible',
        'slug' => 'stripe-visible',
        'price' => 99,
        'interval' => 'month',
        'is_active' => true,
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
    ]);

    Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'user_type' => $user->getMorphClass(),
        'gateway' => 'stripe',
        'gateway_payment_id' => 'cs_paid_visible',
        'amount' => 99,
        'currency' => 'USD',
        'status' => 'completed',
        'description' => 'Stripe Visible subscription',
        'metadata' => [
            'plan_id' => $plan->id,
            'workspace_id' => $workspace->id,
            'receipt_url' => 'https://pay.stripe.com/receipts/test',
        ],
        'paid_at' => now(),
    ]);

    Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'user_type' => $user->getMorphClass(),
        'gateway' => 'stripe',
        'gateway_payment_id' => 'cs_paid_hidden',
        'amount' => 199,
        'currency' => 'USD',
        'status' => 'completed',
        'description' => 'Other workspace subscription',
        'metadata' => [
            'workspace_id' => $otherWorkspace->id,
        ],
        'paid_at' => now(),
    ]);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.subscription.show'))
        ->assertOk()
        ->assertViewHas('billingRows', fn ($rows): bool => $rows->count() === 1
            && str_contains($rows->first()['amount'], '99.00'))
        ->assertSee('Stripe Visible subscription')
        ->assertSee('https://pay.stripe.com/receipts/test', false)
        ->assertDontSee('No billing history yet')
        ->assertDontSee('Other workspace subscription');
});

it('renders the checkout selection page with gateways', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $currencySymbol = html_entity_decode('&#2547;', ENT_QUOTES | ENT_HTML5, 'UTF-8');

    Currency::query()->create([
        'code' => 'BDT',
        'name' => 'Bangladeshi Taka',
        'symbol' => $currencySymbol,
        'exchange_rate' => 1,
        'is_active' => true,
    ]);
    app(PaymentGatewaySettingsService::class)->set('payment_currency', 'BDT');
    app(PaymentGatewaySettingsService::class)->set('stripe_fixed_charge', '1.25');
    app(PaymentGatewaySettingsService::class)->set('stripe_percent_charge', '2.5');

    $plan = Plan::query()->create([
        'name' => 'Premium Upgraded',
        'slug' => 'premium-upgraded',
        'price' => 99,
        'interval' => 'month',
        'is_active' => true,
    ]);

    $workspace->update([
        'settings' => ['onboarding_completed_at' => now()->toIso8601String()],
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
    ]);

    $payment = Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'user_type' => $user->getMorphClass(),
        'gateway' => 'checkout',
        'amount' => 99,
        'currency' => 'USD',
        'status' => 'pending',
        'metadata' => [
            'plan_id' => $plan->id,
            'workspace_id' => $workspace->id,
        ],
    ]);

    $mockManager = Mockery::mock(PaymentGatewayManager::class)->makePartial();
    $mockManager->shouldReceive('getEnabledGatewayNames')->andReturn(['stripe']);
    $this->app->instance(PaymentGatewayManager::class, $mockManager);

    $this->withoutMiddleware([Authorize::class])
        ->actingAs($user)
        ->get(route('user.subscription.checkout.page', $payment))
        ->assertOk()
        ->assertSee('Premium Upgraded')
        ->assertSee($currencySymbol.'99.00')
        ->assertSee('Charge')
        ->assertSee($currencySymbol.'3.73')
        ->assertSee($currencySymbol.'102.73')
        ->assertDontSee('$99.00 USD')
        ->assertSee($payment->uuid);
});

it('redirects to Stripe hosted checkout page when choosing Stripe', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    app(PaymentGatewaySettingsService::class)->set('stripe_fixed_charge', '1.25');
    app(PaymentGatewaySettingsService::class)->set('stripe_percent_charge', '2.5');

    $plan = Plan::query()->create([
        'name' => 'Premium Upgraded',
        'slug' => 'premium-upgraded',
        'price' => 99,
        'interval' => 'month',
        'is_active' => true,
    ]);

    $workspace->update([
        'settings' => ['onboarding_completed_at' => now()->toIso8601String()],
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
    ]);

    $payment = Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'user_type' => $user->getMorphClass(),
        'gateway' => 'checkout',
        'amount' => 99,
        'currency' => 'USD',
        'status' => 'pending',
        'metadata' => [
            'plan_id' => $plan->id,
            'workspace_id' => $workspace->id,
        ],
    ]);

    // Mock PaymentGatewayManager and PaymentService
    $mockManager = Mockery::mock(PaymentGatewayManager::class)->makePartial();
    $mockManager->shouldReceive('getEnabledGatewayNames')->andReturn(['stripe']);
    $this->app->instance(PaymentGatewayManager::class, $mockManager);

    $mockService = Mockery::mock(PaymentService::class);
    $mockService->shouldReceive('charge')
        ->once()
        ->with(
            102.73,
            'USD',
            Mockery::on(fn (array $options): bool => $options['gateway'] === 'stripe'
                && data_get($options, 'metadata.base_amount') === 99.0
                && data_get($options, 'metadata.fixed_charge') === 1.25
                && data_get($options, 'metadata.percent_charge') === 2.5
                && data_get($options, 'metadata.charge_amount') === 3.73
                && data_get($options, 'metadata.payable_amount') === 102.73)
        )
        ->andReturn([
            'payment' => $payment,
            'response' => PaymentResponse::redirect('session_id_123', 'https://checkout.stripe.com/pay/session_id_123'),
        ]);
    $this->app->instance(PaymentService::class, $mockService);

    $response = $this->withoutMiddleware([Authorize::class])
        ->actingAs($user)
        ->post(route('user.subscription.checkout.pay', $payment), [
            'gateway' => 'stripe',
        ]);

    $response->assertRedirect('https://checkout.stripe.com/pay/session_id_123');
});

it('processes webhook and succeeds when matched by payment_intent_id in metadata', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $plan = Plan::query()->create([
        'name' => 'Premium Upgraded',
        'slug' => 'premium-upgraded',
        'price' => 99,
        'interval' => 'month',
        'is_active' => true,
    ]);

    $payment = Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'user_type' => $user->getMorphClass(),
        'gateway' => 'stripe',
        'gateway_payment_id' => 'cs_test_session_id',
        'amount' => 99,
        'currency' => 'USD',
        'status' => 'pending',
        'metadata' => [
            'plan_id' => $plan->id,
            'workspace_id' => $workspace->id,
            'checkout_session_id' => 'cs_test_session_id',
            'payment_intent_id' => 'pi_test_intent_id',
        ],
    ]);

    $log = WebhookLog::create([
        'gateway' => 'stripe',
        'event_type' => 'payment_intent.succeeded',
        'gateway_event_id' => 'evt_test_123',
        'payload' => [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_intent_id',
                ],
            ],
        ],
    ]);

    // Run the job handler synchronously
    $job = new ProcessWebhook($log);
    $job->handle(app(PaymentGatewayManager::class));

    $payment->refresh();
    expect($payment->status)->toEqual('completed');
});

it('sends a subscription expiry reminder once for subscriptions expiring within one day', function (): void {
    createSubscriptionExpiryTemplate('subscription-expiring-soon');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $subscription = subscribeWorkspaceForExpiryTests($workspace->id, [
        'renews_at' => now()->addHours(20),
    ]);

    app(SendSubscriptionExpiryReminderJob::class)->handle(app(SubscriptionAccessService::class));
    app(SendSubscriptionExpiryReminderJob::class)->handle(app(SubscriptionAccessService::class));

    expect(NotificationLog::query()
        ->where('template_slug', 'subscription-expiring-soon')
        ->where('metadata->subscription_id', $subscription->id)
        ->count())->toBe(1);
});

it('does not remind lifetime subscriptions without a renewal date', function (): void {
    createSubscriptionExpiryTemplate('subscription-expiring-soon');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    subscribeWorkspaceForExpiryTests($workspace->id, [
        'renews_at' => null,
    ]);

    app(SendSubscriptionExpiryReminderJob::class)->handle(app(SubscriptionAccessService::class));

    expect(NotificationLog::query()->where('template_slug', 'subscription-expiring-soon')->count())->toBe(0);
});

it('expires past renewal subscriptions and notifies the owner once', function (): void {
    createSubscriptionExpiryTemplate('subscription-expired');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $subscription = subscribeWorkspaceForExpiryTests($workspace->id, [
        'renews_at' => now()->subMinute(),
    ]);

    app(ExpireSubscriptionsJob::class)->handle(app(SubscriptionAccessService::class));
    app(ExpireSubscriptionsJob::class)->handle(app(SubscriptionAccessService::class));

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(NotificationLog::query()
            ->where('template_slug', 'subscription-expired')
            ->where('metadata->subscription_id', $subscription->id)
            ->count())->toBe(1);
});

it('keeps expired workspaces read only while allowing subscription checkout', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $plan = subscribeWorkspaceForExpiryTests($workspace->id, [
        'renews_at' => now()->subDay(),
    ])->plan;

    $this->withoutMiddleware([Authorize::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertSuccessful();

    foreach ([
        'user.campaigns.store',
        'user.contacts.store',
        'user.chatbots.store',
        'user.automations.store',
        'user.message-templates.store',
        'user.leads.generate',
    ] as $routeName) {
        $this->withoutMiddleware([Authorize::class, EnsureTwoFactorAuthenticated::class])
            ->actingAs($user)
            ->post(route($routeName), [])
            ->assertRedirect(route('user.subscription.show'));
    }

    $this->withoutMiddleware([Authorize::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->post(route('user.subscription.checkout.initiate'), [
            'plan_id' => $plan->id,
        ])
        ->assertRedirect();
});

it('blocks plan limits and public chatbot widgets for expired subscriptions', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    subscribeWorkspaceForExpiryTests($workspace->id, [
        'renews_at' => now()->subDay(),
        'limits' => ['max_ai_credits' => 100, 'automation_ai_builder' => true],
    ]);

    expect(app(PlanLimitService::class)->allows($workspace->id, 'max_ai_credits'))->toBeFalse()
        ->and(app(PlanLimitService::class)->featureEnabled($workspace->id, 'automation_ai_builder'))->toBeFalse();

    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Expired Bot',
        'persona' => 'Helpful assistant',
        'is_active' => true,
    ]);
    $widget = ChatbotWidget::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_id' => $chatbot->id,
        'name' => 'Expired Widget',
        'public_token' => 'expired-widget-token',
        'is_active' => true,
    ]);

    $this->postJson(route('widgets.chatbot.sessions', $widget->public_token), [
        'visitor_uid' => 'visitor-1',
    ])->assertForbidden();
});

function createSubscriptionExpiryTemplate(string $slug): NotificationTemplate
{
    return NotificationTemplate::query()->create([
        'slug' => $slug,
        'name' => str($slug)->headline()->toString(),
        'description' => 'Subscription test template',
        'channels' => ['in_app'],
        'variables' => [],
        'in_app_title' => 'Subscription notice',
        'in_app_body' => '{{plan_name}} expires {{expires_at}}',
        'is_active' => true,
    ]);
}

function subscribeWorkspaceForExpiryTests(int $workspaceId, array $overrides = []): Subscription
{
    Workspace::query()
        ->whereKey($workspaceId)
        ->update([
            'settings' => ['onboarding_completed_at' => now()->toIso8601String()],
        ]);

    $plan = Plan::query()->create([
        'name' => 'Expiry Test Plan '.uniqid(),
        'slug' => 'expiry-test-plan-'.uniqid(),
        'description' => 'Expiry test plan',
        'price' => 29,
        'interval' => $overrides['interval'] ?? 'month',
        'limits' => $overrides['limits'] ?? ['max_ai_credits' => 10],
        'features' => [],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    return Subscription::query()->create([
        'workspace_id' => $workspaceId,
        'plan_id' => $plan->id,
        'status' => $overrides['status'] ?? SubscriptionStatus::Active,
        'starts_at' => $overrides['starts_at'] ?? now()->subMonth(),
        'renews_at' => $overrides['renews_at'] ?? now()->addMonth(),
        'ends_at' => $overrides['ends_at'] ?? null,
        'usage' => $overrides['usage'] ?? [],
    ]);
}
