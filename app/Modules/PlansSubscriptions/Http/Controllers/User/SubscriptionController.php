<?php

namespace App\Modules\PlansSubscriptions\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use App\Modules\PaymentGateways\Services\PaymentService;
use App\Modules\PaymentGatewaySettings\Services\PaymentGatewaySettingsService;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Services\Onboarding\OnboardingProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(Request $request, WorkspaceResolver $workspaces, PaymentGatewayManager $gateways): View
    {
        $workspace = $workspaces->current($request->user());
        $subscription = $workspace
            ? Subscription::query()->with('plan')->where('workspace_id', $workspace->id)->latest()->first()
            : null;
        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get();
        $currentPlan = $subscription?->plan ?? $plans->firstWhere('slug', 'free') ?? $plans->first();

        return view('plans-subscriptions::user.subscription', [
            'workspace' => $workspace,
            'subscription' => $subscription,
            'currentPlan' => $currentPlan,
            'plans' => $plans,
            'usageRows' => $this->usageRows($subscription, $currentPlan),
            'featureRows' => $this->featureRows($currentPlan),
            'billingRows' => $this->billingRows($workspace?->id),
            'paymentMethod' => null,
            'enabledPaymentGateways' => $this->gatewayRows($gateways->getEnabledGatewayNames()),
            'statusMeta' => $this->statusMeta($subscription),
        ]);
    }

    private function billingRows(?int $workspaceId): Collection
    {
        if (! $workspaceId) {
            return collect();
        }

        return Payment::query()
            ->where('status', 'completed')
            ->where('metadata->workspace_id', $workspaceId)
            ->latest('paid_at')
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Payment $payment): array => [
                'date' => ($payment->paid_at ?? $payment->created_at)?->format('M d, Y') ?? __('Unknown'),
                'description' => $payment->description ?: __('Subscription payment'),
                'amount' => $payment->formatted_amount,
                'status' => __(str($payment->status)->headline()->toString()),
                'receipt_url' => data_get($payment->metadata, 'receipt_url')
                    ?? data_get($payment->metadata, 'receipt')
                    ?? data_get($payment->metadata, 'hosted_invoice_url'),
            ]);
    }

    /**
     * @param  array<string>  $gateways
     */
    private function gatewayRows(array $gateways): Collection
    {
        return collect($gateways)
            ->map(fn (string $gateway): array => [
                'key' => $gateway,
                'label' => $this->gatewayLabel($gateway),
                'description' => $this->gatewayDescription($gateway),
                'icon' => $this->gatewayIcon($gateway),
            ])
            ->values();
    }

    private function usageRows(?Subscription $subscription, ?Plan $plan): Collection
    {
        $limits = collect($plan?->limits ?? [])
            ->filter(fn (mixed $value, string $key): bool => is_numeric($value) && ! str_contains($key, 'automation_ai_builder'));
        $usage = $subscription?->usage ?? [];

        return $limits->map(function (mixed $limit, string $key) use ($usage): array {
            $used = (int) data_get($usage, $key, 0);
            $maximum = (int) $limit;
            $percent = $maximum > 0 ? min(100, (int) round(($used / $maximum) * 100)) : 0;

            return [
                'key' => $key,
                'label' => $this->limitLabel($key),
                'used' => $used,
                'maximum' => $maximum,
                'percent' => $percent,
                'tone' => $percent >= 90 ? 'warning' : 'primary',
            ];
        })->values();
    }

    private function featureRows(?Plan $plan): Collection
    {
        return collect($plan?->features ?? [])
            ->filter()
            ->values();
    }

    private function statusMeta(?Subscription $subscription): array
    {
        $status = $subscription?->status;

        return match ($status) {
            SubscriptionStatus::Active => ['label' => __('Active'), 'badge' => 'badge-success'],
            SubscriptionStatus::Trialing => ['label' => __('Trialing'), 'badge' => 'badge-soft'],
            SubscriptionStatus::PastDue => ['label' => __('Past due'), 'badge' => 'badge-warning'],
            SubscriptionStatus::Cancelled => ['label' => __('Cancelled'), 'badge' => 'badge-warning'],
            SubscriptionStatus::Expired => ['label' => __('Expired'), 'badge' => 'badge-error'],
            default => ['label' => __('No active subscription'), 'badge' => 'badge-warning'],
        };
    }

    private function limitLabel(string $key): string
    {
        return match ($key) {
            'messages_per_month' => __('Messages / month'),
            'contacts' => __('Contacts'),
            'whatsapp_numbers' => __('WhatsApp numbers'),
            'ai_tokens' => __('AI tokens'),
            'campaigns_per_month' => __('Campaigns / month'),
            'chatbots' => __('Chatbots'),
            'team_members', 'team_seats' => __('Team seats'),
            'max_lead_generations_per_month' => __('Lead generations / month'),
            'max_ai_lead_results_per_month' => __('Generated leads / month'),
            'max_ai_credits' => __('Platform AI credits'),
            default => __(str($key)->replace('_', ' ')->title()->toString()),
        };
    }

    private function gatewayLabel(string $gateway): string
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

    private function gatewayDescription(string $gateway): string
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

    private function gatewayIcon(string $gateway): string
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

    /**
     * @return array{base_amount: float, fixed_charge: float, percent_charge: float, charge_amount: float, payable_amount: float}
     */
    private function gatewayChargeBreakdown(float $baseAmount, string $gateway): array
    {
        $settings = app(PaymentGatewaySettingsService::class);
        $fixedCharge = (float) $settings->get("{$gateway}_fixed_charge", 0);
        $percentCharge = (float) $settings->get("{$gateway}_percent_charge", 0);
        $chargeAmount = round($fixedCharge + ($baseAmount * $percentCharge / 100), 2);

        return [
            'base_amount' => $baseAmount,
            'fixed_charge' => $fixedCharge,
            'percent_charge' => $percentCharge,
            'charge_amount' => $chargeAmount,
            'payable_amount' => round($baseAmount + $chargeAmount, 2),
        ];
    }

    public function initiateCheckout(Request $request, WorkspaceResolver $workspaces): RedirectResponse
    {
        $workspace = $workspaces->current($request->user());

        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->where('is_active', true)->findOrFail($data['plan_id']);

        // Create a pending payment
        $payment = Payment::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->getKey(),
            'user_type' => $request->user()->getMorphClass(),
            'gateway' => 'checkout',
            'amount' => $plan->price,
            'currency' => currency_default_code(),
            'status' => 'pending',
            'description' => __(':plan subscription', ['plan' => $plan->name]),
            'metadata' => [
                'plan_id' => $plan->id,
                'workspace_id' => $workspace->id,
                'onboarding' => false,
                'checkout_only' => true,
            ],
        ]);

        return redirect()->route('user.subscription.checkout.page', $payment);
    }

    public function checkoutPage(Request $request, Payment $payment, WorkspaceResolver $workspaces, PaymentGatewayManager $gateways): View|RedirectResponse
    {
        $workspace = $workspaces->current($request->user());

        if ((int) data_get($payment->metadata, 'workspace_id') !== $workspace->id) {
            abort(404);
        }

        if ($payment->status === 'completed') {
            return redirect()->route('user.subscription.show')->with('success', __('Your subscription is active.'));
        }

        $enabledGateways = $payment->gateway === 'checkout' ? $gateways->getEnabledGatewayNames() : [];
        $gatewayCharges = collect($enabledGateways)
            ->mapWithKeys(fn (string $gateway): array => [
                $gateway => $this->gatewayChargeBreakdown((float) $payment->default_amount, $gateway),
            ])
            ->all();

        return view('plans-subscriptions::user.checkout', [
            'payment' => $payment,
            'plan' => Plan::find(data_get($payment->metadata, 'plan_id')),
            'gateways' => $enabledGateways,
            'gatewayCharges' => $gatewayCharges,
        ]);
    }

    public function pay(Request $request, Payment $payment, WorkspaceResolver $workspaces, PaymentService $payments, PaymentGatewayManager $gateways): RedirectResponse
    {
        $workspace = $workspaces->current($request->user());

        if ((int) data_get($payment->metadata, 'workspace_id') !== $workspace->id) {
            abort(404);
        }

        if ($payment->status === 'completed') {
            $plan = Plan::findOrFail(data_get($payment->metadata, 'plan_id'));
            app(OnboardingProgress::class)->activatePlan($workspace, $plan);

            return redirect()->route('user.subscription.show')->with('success', __('Your subscription is active.'));
        }

        if ($payment->gateway !== 'checkout') {
            return redirect()->route('user.subscription.checkout.page', $payment)
                ->with('error', __('This payment is already being processed. Please complete the selected payment method.'));
        }

        $enabledGateways = $gateways->getEnabledGatewayNames();

        if ($enabledGateways === []) {
            return redirect()->route('user.subscription.checkout.page', $payment)
                ->with('error', __('No active payment gateways are available. Please contact support.'));
        }

        $data = $request->validate([
            'gateway' => ['required', Rule::in($enabledGateways)],
        ]);

        $plan = Plan::query()->where('is_active', true)->findOrFail(data_get($payment->metadata, 'plan_id'));

        $chargeBreakdown = $this->gatewayChargeBreakdown((float) $plan->price, $data['gateway']);

        $payment->update(['status' => 'processing']);

        $result = $payments->charge($chargeBreakdown['payable_amount'], $payment->currency, [
            'gateway' => $data['gateway'],
            'description' => __(':plan subscription', ['plan' => $plan->name]),
            'return_url' => route('user.subscription.payment.return', ['gateway' => $data['gateway']]),
            'cancel_url' => route('user.subscription.payment.cancel'),
            'metadata' => [
                'plan_id' => $plan->id,
                'workspace_id' => $workspace->id,
                'onboarding' => false,
                'checkout_payment_id' => $payment->id,
                'selected_gateway' => $data['gateway'],
                ...$chargeBreakdown,
            ],
        ]);

        $gatewayPayment = $result['payment'];
        $response = $result['response'];

        if ($response->isComplete()) {
            app(OnboardingProgress::class)->activatePlan($workspace, $plan);

            return redirect()->route('user.subscription.show')->with('success', __('Your subscription is active.'));
        }

        if ($response->isRedirect()) {
            return redirect()->away($response->redirectUrl);
        }

        return redirect()->route('user.subscription.checkout.page', $gatewayPayment);
    }

    public function paymentReturn(Request $request, PaymentService $payments, WorkspaceResolver $workspaces): RedirectResponse
    {
        $payment = $payments->verify($request, $request->query('gateway'));
        $workspace = $workspaces->current($request->user());

        if ((int) data_get($payment->metadata, 'workspace_id') !== $workspace->id) {
            abort(404);
        }

        if ($payment->status === 'completed') {
            $plan = Plan::findOrFail(data_get($payment->metadata, 'plan_id'));
            app(OnboardingProgress::class)->activatePlan($workspace, $plan);

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
}
