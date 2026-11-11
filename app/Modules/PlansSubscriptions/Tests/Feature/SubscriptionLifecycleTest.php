<?php

use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\Admin;
use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\PaymentGateways\Events\PaymentSucceeded;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Enums\CheckoutIntent;
use App\Modules\PlansSubscriptions\Enums\SubscriptionEventType;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Jobs\ExpireSubscriptionsJob;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Models\SubscriptionEvent;
use App\Modules\PlansSubscriptions\Services\PlanCheckoutService;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use App\Modules\PlansSubscriptions\Services\SubscriptionManager;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\WithoutDefaultTestPlan;

uses(RefreshDatabase::class, WithoutDefaultTestPlan::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

function lifecyclePlan(array $attributes = []): Plan
{
    return Plan::query()->create([
        'name' => $attributes['name'] ?? 'Plan '.Str::random(6),
        'slug' => $attributes['slug'] ?? 'plan-'.Str::lower(Str::random(8)),
        'price' => $attributes['price'] ?? 30,
        'interval' => $attributes['interval'] ?? Plan::INTERVAL_MONTH,
        'is_free' => $attributes['is_free'] ?? false,
        'limits' => $attributes['limits'] ?? [],
        'features' => [],
        'is_active' => true,
        'sort_order' => $attributes['sort_order'] ?? 10,
    ]);
}

/**
 * @return array<int, class-string>
 */
function adminTestMiddleware(): array
{
    return [PanelAccess::class, EnsureTwoFactorAuthenticated::class, PermissionMiddleware::class, Authorize::class];
}

function lifecycleWorkspace(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $workspace->update(['settings' => ['onboarding_completed_at' => now()->toIso8601String()]]);

    return [$user, $workspace->fresh()];
}

function completedPlanPayment(User $user, Workspace $workspace, Plan $plan, CheckoutIntent $intent, array $metadata = []): Payment
{
    return Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'user_type' => $user->getMorphClass(),
        'gateway' => 'manual',
        'amount' => $metadata['amount'] ?? $plan->price,
        'currency' => 'USD',
        'status' => 'completed',
        'description' => $plan->name.' subscription',
        'metadata' => ['plan_id' => $plan->id, 'workspace_id' => $workspace->id, 'intent' => $intent->value, ...$metadata],
        'paid_at' => now(),
    ]);
}

it('gives a new workspace the free plan automatically', function (): void {
    lifecyclePlan(['slug' => 'free', 'price' => 0, 'is_free' => true]);

    [, $workspace] = lifecycleWorkspace();
    $subscription = app(SubscriptionAccessService::class)->currentForWorkspace($workspace->id);

    expect($subscription)->not->toBeNull()
        ->and($subscription->plan->slug)->toBe('free')
        ->and($subscription->renews_at)->toBeNull()
        ->and(app(SubscriptionAccessService::class)->canUseServices($workspace->id))->toBeTrue();
});

it('treats a workspace without a subscription as not usable', function (): void {
    [, $workspace] = lifecycleWorkspace();

    expect(app(SubscriptionAccessService::class)->canUseServices($workspace->id))->toBeFalse()
        ->and(app(SubscriptionAccessService::class)->isExpiredForUse($workspace->id))->toBeFalse();
});

it('activates a plan from a completed payment exactly once even when the event fires twice', function (): void {
    [$user, $workspace] = lifecycleWorkspace();
    $plan = lifecyclePlan();
    $payment = completedPlanPayment($user, $workspace, $plan, CheckoutIntent::New);

    event(new PaymentSucceeded($payment));
    event(new PaymentSucceeded($payment));

    $subscription = Subscription::query()->where('workspace_id', $workspace->id)->firstOrFail();

    expect($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->renews_at->isSameDay(now()->addMonth()))->toBeTrue()
        ->and(SubscriptionEvent::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and(data_get($payment->fresh()->metadata, 'invoice_number'))->toStartWith('INV-'.now()->format('Y').'-');
});

it('extends an early renewal from the current renewal date so no paid days are lost', function (): void {
    [$user, $workspace] = lifecycleWorkspace();
    $plan = lifecyclePlan();
    app(SubscriptionManager::class)->activate($workspace, $plan);
    $renewsAt = Subscription::query()->where('workspace_id', $workspace->id)->value('renews_at');

    Carbon::setTestNow(now()->addDays(20));
    app(SubscriptionManager::class)->applyPayment(completedPlanPayment($user, $workspace, $plan, CheckoutIntent::Renew));

    $subscription = Subscription::query()->where('workspace_id', $workspace->id)->firstOrFail();

    expect($subscription->renews_at->toDateString())->toBe(Carbon::parse($renewsAt)->addMonthNoOverflow()->toDateString());
});

it('quotes an upgrade with credit for the unused part of the current period', function (): void {
    Carbon::setTestNow('2026-03-01 00:00:00');
    [, $workspace] = lifecycleWorkspace();
    $basic = lifecyclePlan(['price' => 30]);
    $pro = lifecyclePlan(['price' => 100]);
    app(SubscriptionManager::class)->activate($workspace, $basic);

    Carbon::setTestNow('2026-03-16 12:00:00');
    $quote = app(PlanCheckoutService::class)->quote($workspace, $pro);

    expect($quote['intent'])->toBe(CheckoutIntent::Upgrade)
        ->and($quote['credit'])->toBe('15.00')
        ->and($quote['amount_due'])->toBe('85.00');
});

it('applies a paid upgrade immediately and keeps usage', function (): void {
    [$user, $workspace] = lifecycleWorkspace();
    $basic = lifecyclePlan(['price' => 30]);
    $pro = lifecyclePlan(['price' => 100, 'interval' => Plan::INTERVAL_YEAR]);
    app(SubscriptionManager::class)->activate($workspace, $basic);
    app(PlanLimitService::class)->consume($workspace->id, 'messages_per_month', 7);

    app(SubscriptionManager::class)->applyPayment(completedPlanPayment($user, $workspace, $pro, CheckoutIntent::Upgrade));

    $subscription = Subscription::query()->where('workspace_id', $workspace->id)->firstOrFail();

    expect($subscription->plan_id)->toBe($pro->id)
        ->and($subscription->renews_at->isSameDay(now()->addYear()))->toBeTrue()
        ->and(data_get($subscription->usage, 'messages_per_month'))->toBe(7)
        ->and($subscription->events()->first()->type)->toBe(SubscriptionEventType::Upgraded);
});

it('moves to the free plan only when the paid period ends', function (): void {
    [$user, $workspace] = lifecycleWorkspace();
    $free = lifecyclePlan(['price' => 0, 'is_free' => true]);
    $paid = lifecyclePlan(['price' => 30]);
    app(SubscriptionManager::class)->activate($workspace, $paid);

    $this->withoutMiddleware([Authorize::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->post(route('user.subscription.checkout.initiate'), ['plan_id' => $free->id])
        ->assertRedirect();

    $subscription = Subscription::query()->where('workspace_id', $workspace->id)->firstOrFail();
    expect($subscription->plan_id)->toBe($paid->id)->and($subscription->pending_plan_id)->toBe($free->id);

    Carbon::setTestNow(now()->addMonth()->addHour());
    app(ExpireSubscriptionsJob::class)->handle(app(SubscriptionAccessService::class));

    $subscription->refresh();

    expect($subscription->plan_id)->toBe($free->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->renews_at)->toBeNull()
        ->and(app(SubscriptionAccessService::class)->canUseServices($workspace->id))->toBeTrue();
});

it('starts a prepaid cheaper plan when the current period ends', function (): void {
    [$user, $workspace] = lifecycleWorkspace();
    $pro = lifecyclePlan(['price' => 100]);
    $basic = lifecyclePlan(['price' => 30]);
    app(SubscriptionManager::class)->activate($workspace, $pro);
    $periodEnd = Subscription::query()->where('workspace_id', $workspace->id)->value('renews_at');

    app(SubscriptionManager::class)->applyPayment(completedPlanPayment($user, $workspace, $basic, CheckoutIntent::Downgrade));
    expect(Subscription::query()->where('workspace_id', $workspace->id)->value('plan_id'))->toBe($pro->id);

    Carbon::setTestNow(Carbon::parse($periodEnd)->addHour());
    app(ExpireSubscriptionsJob::class)->handle(app(SubscriptionAccessService::class));

    $subscription = Subscription::query()->where('workspace_id', $workspace->id)->firstOrFail();

    expect($subscription->plan_id)->toBe($basic->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->renews_at->toDateString())->toBe(Carbon::parse($periodEnd)->addMonthNoOverflow()->toDateString());
});

it('expires a subscription cancelled at period end as cancelled', function (): void {
    [, $workspace] = lifecycleWorkspace();
    $subscription = app(SubscriptionManager::class)->activate($workspace, lifecyclePlan());
    app(SubscriptionManager::class)->cancel($subscription, true);

    expect(app(SubscriptionAccessService::class)->canUseServices($workspace->id))->toBeTrue();

    Carbon::setTestNow(now()->addMonth()->addHour());
    app(ExpireSubscriptionsJob::class)->handle(app(SubscriptionAccessService::class));

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(app(SubscriptionAccessService::class)->canUseServices($workspace->id))->toBeFalse();
});

it('never expires the free plan and blocks once its quota is used', function (): void {
    [, $workspace] = lifecycleWorkspace();
    $free = lifecyclePlan(['price' => 0, 'is_free' => true, 'limits' => ['messages_per_month' => 2, 'chatbots' => 0]]);
    app(SubscriptionManager::class)->activate($workspace, $free);
    $limits = app(PlanLimitService::class);

    expect($limits->allows($workspace->id, 'max_messages_per_month', 2))->toBeTrue()
        ->and($limits->allows($workspace->id, 'chatbots'))->toBeFalse()
        ->and($limits->allows($workspace->id, 'campaigns_per_month', 500))->toBeTrue();

    $limits->consume($workspace->id, 'messages_per_month', 2);

    Carbon::setTestNow(now()->addYears(2)->subDay());
    app(ExpireSubscriptionsJob::class)->handle(app(SubscriptionAccessService::class));

    expect(app(SubscriptionAccessService::class)->canUseServices($workspace->id))->toBeTrue();

    Carbon::setTestNow(now()->subYears(2)->addDays(2));
    expect($limits->allows($workspace->id, 'messages_per_month'))->toBeFalse();
});

it('resets counted quotas each monthly usage period', function (): void {
    Carbon::setTestNow('2026-01-10 09:00:00');
    [, $workspace] = lifecycleWorkspace();
    app(SubscriptionManager::class)->activate($workspace, lifecyclePlan(['interval' => Plan::INTERVAL_YEAR, 'limits' => ['max_ai_credits' => 5]]));
    $limits = app(PlanLimitService::class);

    $limits->consume($workspace->id, 'max_ai_credits', 5);
    expect($limits->allows($workspace->id, 'max_ai_credits'))->toBeFalse();

    Carbon::setTestNow('2026-02-10 09:00:01');
    expect($limits->allows($workspace->id, 'max_ai_credits'))->toBeTrue()
        ->and($limits->remaining($workspace->id, 'max_ai_credits'))->toBe(5);

    $limits->consume($workspace->id, 'max_ai_credits', 1);
    expect(data_get(Subscription::query()->where('workspace_id', $workspace->id)->value('usage'), 'max_ai_credits'))->toBe(1);
});

it('sends the 80 and 100 percent usage alerts once each', function (): void {
    NotificationTemplate::query()->create([
        'slug' => 'subscription-usage-limit',
        'name' => 'Usage',
        'channels' => ['in_app'],
        'variables' => [],
        'in_app_title' => '{{limit_name}} {{percent}}%',
        'in_app_body' => '{{used}} / {{limit}}',
        'is_active' => true,
    ]);
    [, $workspace] = lifecycleWorkspace();
    $subscription = app(SubscriptionManager::class)->activate($workspace, lifecyclePlan(['limits' => ['messages_per_month' => 10]]));
    $limits = app(PlanLimitService::class);

    $limits->consume($workspace->id, 'messages_per_month', 7);
    $limits->consume($workspace->id, 'messages_per_month', 1);
    $limits->consume($workspace->id, 'messages_per_month', 1);
    $limits->consume($workspace->id, 'messages_per_month', 1);
    $limits->consume($workspace->id, 'messages_per_month', 1);

    $alerts = SubscriptionEvent::query()->where('subscription_id', $subscription->id)->where('type', SubscriptionEventType::LimitAlert->value)->get();

    expect($alerts->pluck('meta.threshold')->sort()->values()->all())->toBe([80, 100])
        ->and(NotificationLog::query()->where('template_slug', 'subscription-usage-limit')->where('metadata->subscription_id', $subscription->id)->count())->toBe(2);
});

it('shows invoices only to the workspace that paid', function (): void {
    [$user, $workspace] = lifecycleWorkspace();
    [$otherUser] = lifecycleWorkspace();
    $plan = lifecyclePlan(['name' => 'Invoice Plan']);
    $payment = completedPlanPayment($user, $workspace, $plan, CheckoutIntent::New);
    event(new PaymentSucceeded($payment));

    $this->withoutMiddleware([Authorize::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->get(route('user.subscription.invoices.show', $payment))
        ->assertOk()
        ->assertSee(data_get($payment->fresh()->metadata, 'invoice_number'))
        ->assertSee('Invoice Plan');

    $this->withoutMiddleware([Authorize::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($otherUser)
        ->get(route('user.subscription.invoices.show', $payment))
        ->assertNotFound();
});

it('lets an admin change, extend and cancel a subscription with history', function (): void {
    [, $workspace] = lifecycleWorkspace();
    $basic = lifecyclePlan(['price' => 30]);
    $pro = lifecyclePlan(['price' => 100]);
    $subscription = app(SubscriptionManager::class)->activate($workspace, $basic);
    $admin = Admin::factory()->create();

    $this->withoutMiddleware(adminTestMiddleware())->actingAs($admin, 'admin')
        ->post(route('admin.subscriptions.change-plan', $subscription), ['plan_id' => $pro->id, 'restart_period' => '0', 'note' => 'Comp'])
        ->assertRedirect();
    $this->withoutMiddleware(adminTestMiddleware())->actingAs($admin, 'admin')
        ->post(route('admin.subscriptions.extend', $subscription), ['days' => 10])
        ->assertRedirect();
    $this->withoutMiddleware(adminTestMiddleware())->actingAs($admin, 'admin')
        ->post(route('admin.subscriptions.cancel', $subscription), ['at_period_end' => '0'])
        ->assertRedirect();

    $subscription->refresh();
    $types = $subscription->events()->pluck('type')->map->value->all();

    expect($subscription->plan_id)->toBe($pro->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($types)->toContain('admin_changed', 'extended', 'cancelled')
        ->and($subscription->events()->where('type', 'extended')->first()->actor_id)->toBe($admin->id);

    $this->withoutMiddleware(adminTestMiddleware())->actingAs($admin, 'admin')
        ->get(route('admin.subscriptions.show', $subscription))
        ->assertOk()
        ->assertSee('Comp');
});

it('keeps plans with subscribers by deactivating instead of deleting', function (): void {
    [, $workspace] = lifecycleWorkspace();
    $plan = lifecyclePlan();
    app(SubscriptionManager::class)->activate($workspace, $plan);

    $this->withoutMiddleware(adminTestMiddleware())->actingAs(Admin::factory()->create(), 'admin')
        ->delete(route('admin.plans.destroy', $plan))
        ->assertRedirect(route('admin.plans.index'));

    expect($plan->fresh())->not->toBeNull()->and($plan->fresh()->is_active)->toBeFalse();
});
