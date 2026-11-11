<?php

namespace App\Modules\PlansSubscriptions\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use App\Modules\PaymentGateways\Services\PaymentService;
use App\Modules\PlansSubscriptions\Enums\CheckoutIntent;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Http\Requests\InitiateCheckoutRequest;
use App\Modules\PlansSubscriptions\Http\Requests\PayCheckoutRequest;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\PlansSubscriptions\Services\PlanCheckoutService;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use App\Modules\PlansSubscriptions\Services\SubscriptionManager;
use App\Modules\Workspaces\Models\Workspace;
use App\Services\Navigation\WorkspaceNavigationRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public const RENEW_WINDOW_DAYS = 7;

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected SubscriptionAccessService $access,
        protected PlanCheckoutService $checkout,
    ) {}

    public function show(Request $request, PaymentGatewayManager $gateways, PlanLimitService $limits): View
    {
        $workspace = $this->workspaces->current($request->user());
        $subscription = $workspace ? $this->access->currentForWorkspace($workspace->id)?->load('pendingPlan') : null;
        $plans = Plan::query()->active()->orderBy('sort_order')->get();
        $currentPlan = $subscription?->plan ?? $plans->first(fn (Plan $plan): bool => $plan->isFree()) ?? $plans->first();

        return view('plans-subscriptions::user.subscription', [
            'workspace' => $workspace,
            'subscription' => $subscription,
            'currentPlan' => $currentPlan,
            'plans' => $plans,
            'planOptions' => $this->planOptions($workspace, $subscription, $plans),
            'usageRows' => $subscription ? collect($limits->usageSnapshot($subscription)) : collect(),
            'usageResetsAt' => $subscription ? $limits->currentPeriodStart($subscription)->addMonthNoOverflow() : null,
            'featureRows' => collect($currentPlan?->features ?? [])->filter()->values(),
            'billingRows' => $this->billingRows($workspace?->id),
            'paymentMethod' => null,
            'enabledPaymentGateways' => $this->gatewayRows($gateways->getEnabledGatewayNames()),
            'statusMeta' => $this->statusMeta($subscription),
            'isUsable' => $subscription && $this->access->subscriptionIsUsable($subscription),
            'canRenew' => $this->canRenew($subscription),
            'planModules' => collect(app(WorkspaceNavigationRegistry::class)->featureCatalog())
                ->reject(fn (array $module): bool => $module['locked'])
                ->map(fn (array $module): string => $module['label'])
                ->all(),
        ]);
    }

    public function initiateCheckout(InitiateCheckoutRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $plan = Plan::query()->active()->findOrFail($request->integer('plan_id'));
        $subscription = $this->access->currentForWorkspace($workspace->id);

        if ($plan->isFree()) {
            return $this->switchToFree($workspace, $subscription, $plan, $request);
        }

        $payment = $this->checkout->start($request->user(), $workspace, $plan);

        return redirect()->route('user.subscription.checkout.page', $payment);
    }

    public function checkoutPage(Request $request, Payment $payment, PaymentGatewayManager $gateways): View|RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        abort_unless($this->checkout->belongsTo($payment, $workspace), 404);

        if ($payment->status === 'completed' || $payment->status === 'superseded') {
            return redirect()->route('user.subscription.show')->with('success', __('Your payment was received and your subscription is updated.'));
        }

        $enabledGateways = $payment->gateway === 'checkout' ? $gateways->getEnabledGatewayNames() : [];

        return view('plans-subscriptions::user.checkout', [
            'payment' => $payment,
            'plan' => Plan::query()->find(data_get($payment->metadata, 'plan_id')),
            'intent' => CheckoutIntent::tryFrom((string) data_get($payment->metadata, 'intent')) ?? CheckoutIntent::New,
            'gateways' => $enabledGateways,
            'gatewayCharges' => collect($enabledGateways)
                ->mapWithKeys(fn (string $gateway): array => [$gateway => $this->checkout->chargeBreakdown((string) $payment->amount, $gateway)])
                ->all(),
        ]);
    }

    public function pay(PayCheckoutRequest $request, Payment $payment): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        abort_unless($this->checkout->belongsTo($payment, $workspace), 404);

        if ($payment->status === 'completed') {
            return redirect()->route('user.subscription.show')->with('success', __('Your subscription is active.'));
        }

        if ($payment->gateway !== 'checkout' || $payment->status === 'superseded') {
            return redirect()->route('user.subscription.checkout.page', $payment)
                ->with('error', __('This payment is already being processed. Please complete the selected payment method.'));
        }

        $gateway = $request->string('gateway')->toString();
        $result = $this->checkout->pay(
            $payment,
            $gateway,
            route('user.subscription.payment.return', ['gateway' => $gateway]),
            route('user.subscription.payment.cancel'),
        );

        if ($result['response']->isComplete()) {
            return redirect()->route('user.subscription.show')->with('success', __('Your subscription is active.'));
        }

        if ($result['response']->isRedirect()) {
            return redirect()->away($result['response']->redirectUrl);
        }

        return redirect()->route('user.subscription.checkout.page', $result['payment']);
    }

    public function paymentReturn(Request $request, PaymentService $payments): RedirectResponse
    {
        $payment = $payments->verify($request, $request->query('gateway'));
        $workspace = $this->workspaces->current($request->user());
        abort_unless($this->checkout->belongsTo($payment, $workspace), 404);

        if ($payment->status === 'completed') {
            return redirect()->route('user.subscription.show')->with('success', __('Your subscription is active.'));
        }

        return redirect()->route('user.subscription.checkout.page', $payment)
            ->with('error', __('Payment is still pending. Please complete the payment to continue.'));
    }

    public function paymentCancel(): RedirectResponse
    {
        return redirect()->route('user.subscription.show')
            ->with('error', __('Payment was cancelled. You can choose a plan again.'));
    }

    public function cancel(Request $request, SubscriptionManager $manager): RedirectResponse
    {
        $subscription = $this->currentOrFail($request);

        if (! $this->access->subscriptionIsUsable($subscription) || ! $subscription->renews_at) {
            return back()->with('error', __('This plan cannot be cancelled.'));
        }

        $manager->cancel($subscription, true, $request->user());

        return back()->with('success', __('Your plan will end on :date and will not renew.', ['date' => $subscription->renews_at->format('M d, Y')]));
    }

    public function resume(Request $request, SubscriptionManager $manager): RedirectResponse
    {
        $manager->resume($this->currentOrFail($request), $request->user());

        return back()->with('success', __('Your plan change has been undone.'));
    }

    protected function switchToFree(Workspace $workspace, ?Subscription $subscription, Plan $plan, Request $request): RedirectResponse
    {
        $manager = app(SubscriptionManager::class);

        if ($subscription?->plan_id === $plan->id && $this->access->subscriptionIsUsable($subscription)) {
            return back()->with('success', __('You are already on the :plan plan.', ['plan' => $plan->name]));
        }

        if ($subscription && $subscription->renews_at?->isFuture() && $this->access->subscriptionIsUsable($subscription)) {
            $manager->scheduleDowngrade($subscription, $plan, $request->user());

            return back()->with('success', __('You will move to the :plan plan on :date.', [
                'plan' => $plan->name,
                'date' => $subscription->renews_at->format('M d, Y'),
            ]));
        }

        $manager->activate($workspace, $plan, null, $request->user());

        return back()->with('success', __('The :plan plan is now active.', ['plan' => $plan->name]));
    }

    protected function currentOrFail(Request $request): Subscription
    {
        $workspace = $this->workspaces->current($request->user());

        return $this->access->currentForWorkspace($workspace->id) ?? abort(404);
    }

    protected function canRenew(?Subscription $subscription): bool
    {
        $plan = $subscription?->plan;

        if (! $subscription || ! $plan || $plan->neverExpires() || ! $plan->is_active) {
            return false;
        }

        $expiresAt = $this->access->expiryDate($subscription);

        return ! $this->access->subscriptionIsUsable($subscription)
            || ($expiresAt !== null && $expiresAt->lte(now()->addDays(self::RENEW_WINDOW_DAYS)));
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @return Collection<int, array{plan: Plan, intent: string, label: string, amount_due: string, credit: string, disabled: bool}>
     */
    protected function planOptions(?Workspace $workspace, ?Subscription $subscription, Collection $plans): Collection
    {
        $usable = $subscription && $this->access->subscriptionIsUsable($subscription);

        return $plans->map(function (Plan $plan) use ($workspace, $subscription, $usable): array {
            $quote = $workspace && ! $plan->isFree()
                ? $this->checkout->quote($workspace, $plan)
                : ['intent' => $this->checkout->intentFor($subscription, $plan), 'credit' => '0.00', 'amount_due' => '0.00'];
            $intent = $quote['intent'];
            $isCurrent = $usable && $subscription->plan_id === $plan->id;
            $lifetimeLocked = $usable && $subscription->plan?->isLifetime() && ! $isCurrent;

            return [
                'plan' => $plan,
                'intent' => $intent->value,
                'label' => match (true) {
                    $isCurrent && $this->canRenew($subscription) => __('Renew'),
                    $isCurrent => __('Current plan'),
                    $intent === CheckoutIntent::Upgrade => __('Upgrade'),
                    $intent === CheckoutIntent::Downgrade => __('Switch at period end'),
                    default => __('Choose plan'),
                },
                'amount_due' => $quote['amount_due'],
                'credit' => $quote['credit'],
                'disabled' => $lifetimeLocked || ($isCurrent && ! $this->canRenew($subscription)),
            ];
        });
    }

    protected function billingRows(?int $workspaceId): Collection
    {
        if (! $workspaceId) {
            return collect();
        }

        return Payment::query()
            ->where('status', 'completed')
            ->where('metadata->workspace_id', $workspaceId)
            ->whereNotNull('metadata->plan_id')
            ->latest('paid_at')
            ->latest()
            ->limit(24)
            ->get()
            ->map(fn (Payment $payment): array => [
                'date' => ($payment->paid_at ?? $payment->created_at)?->format('M d, Y') ?? __('Unknown'),
                'number' => data_get($payment->metadata, 'invoice_number'),
                'description' => $payment->description ?: __('Subscription payment'),
                'amount' => $payment->formatted_amount,
                'status' => __(str($payment->status)->headline()->toString()),
                'invoice_url' => route('user.subscription.invoices.show', $payment),
                'receipt_url' => data_get($payment->metadata, 'receipt_url')
                    ?? data_get($payment->metadata, 'hosted_invoice_url'),
            ]);
    }

    /**
     * @param  array<string>  $gateways
     */
    protected function gatewayRows(array $gateways): Collection
    {
        return collect($gateways)
            ->map(fn (string $gateway): array => [
                'key' => $gateway,
                'label' => self::gatewayLabel($gateway),
                'description' => self::gatewayDescription($gateway),
                'icon' => self::gatewayIcon($gateway),
            ])
            ->values();
    }

    /**
     * @return array{label: string, badge: string}
     */
    protected function statusMeta(?Subscription $subscription): array
    {
        if ($subscription && $subscription->hasUsableStatus() && ! $this->access->subscriptionIsUsable($subscription)) {
            return ['label' => __('Expired'), 'badge' => 'badge-error'];
        }

        if ($subscription?->cancel_at_period_end) {
            return ['label' => __('Ends at period end'), 'badge' => 'badge-warning'];
        }

        return match ($subscription?->status) {
            SubscriptionStatus::Active => ['label' => __('Active'), 'badge' => 'badge-success'],
            SubscriptionStatus::Trialing => ['label' => __('Trialing'), 'badge' => 'badge-soft'],
            SubscriptionStatus::PastDue => ['label' => __('Past due'), 'badge' => 'badge-warning'],
            SubscriptionStatus::Cancelled => ['label' => __('Cancelled'), 'badge' => 'badge-warning'],
            SubscriptionStatus::Expired => ['label' => __('Expired'), 'badge' => 'badge-error'],
            default => ['label' => __('No active subscription'), 'badge' => 'badge-warning'],
        };
    }

    public static function gatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'razorpay' => 'Razorpay',
            'sslcommerz' => 'SSLCommerz',
            'paystack' => 'Paystack',
            'flutterwave' => 'Flutterwave',
            default => str($gateway)->replace(['-', '_'], ' ')->title()->toString(),
        };
    }

    public static function gatewayDescription(string $gateway): string
    {
        return match ($gateway) {
            'stripe' => __('Credit and debit cards'),
            'paypal' => __('PayPal account or card'),
            'razorpay' => __('Cards, netbanking, and UPI'),
            'sslcommerz' => __('Mobile and local payments'),
            'paystack' => __('Cards, bank, and mobile money'),
            'flutterwave' => __('Global cards and mobile money'),
            default => __('Manual or offline payment'),
        };
    }

    public static function gatewayIcon(string $gateway): string
    {
        return match ($gateway) {
            'stripe' => 'ph-credit-card',
            'paypal' => 'ph-paypal-logo',
            'razorpay' => 'ph-wallet',
            'sslcommerz' => 'ph-shield-check',
            'paystack' => 'ph-bank',
            'flutterwave' => 'ph-lightning',
            default => 'ph-currency-dollar',
        };
    }
}
