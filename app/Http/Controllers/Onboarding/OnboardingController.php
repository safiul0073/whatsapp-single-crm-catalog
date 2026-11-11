<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use App\Modules\PaymentGateways\Services\PaymentService;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Services\PlanCheckoutService;
use App\Services\Onboarding\OnboardingProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingProgress $onboarding
    ) {}

    public function workspace(Request $request): View|RedirectResponse
    {
        $workspace = $this->onboarding->workspaceFor($request->user());

        if ($this->onboarding->workspaceIsComplete($workspace)) {
            return redirect()->route('onboarding.plan');
        }

        return view('onboarding.workspace', [
            'workspace' => $workspace,
            'categories' => $this->categories(),
            'teamSizes' => $this->teamSizes(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function storeWorkspace(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:80'],
            'team_size' => ['required', 'string', 'max:40'],
            'timezone' => ['required', 'timezone'],
        ]);

        $workspace = $this->onboarding->workspaceFor($request->user());
        $this->onboarding->completeWorkspace($workspace, $data);

        return redirect()->route('onboarding.plan');
    }

    public function plan(Request $request): View|RedirectResponse
    {
        $workspace = $this->onboarding->workspaceFor($request->user());

        if (! $this->onboarding->workspaceIsComplete($workspace)) {
            return redirect()->route('onboarding.workspace');
        }

        if ($this->onboarding->subscriptionIsComplete($workspace)) {
            return redirect()->route('user.dashboard');
        }

        return view('onboarding.plan', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $workspace = $this->onboarding->workspaceFor($request->user());

        if (! $this->onboarding->workspaceIsComplete($workspace)) {
            return redirect()->route('onboarding.workspace');
        }

        $plan = Plan::query()->where('is_active', true)->findOrFail($data['plan_id']);

        if ($plan->isFree()) {
            $this->onboarding->activatePlan($workspace, $plan, SubscriptionStatus::Active->value);

            return redirect()->route('user.dashboard')->with('success', __('Your free plan is active.'));
        }

        $payment = app(PlanCheckoutService::class)->start($request->user(), $workspace, $plan, onboarding: true);

        return redirect()->route('onboarding.checkout', $payment);
    }

    public function checkout(Request $request, Payment $payment, PaymentGatewayManager $gateways): View|RedirectResponse
    {
        $workspace = $this->onboarding->workspaceFor($request->user());

        if ((int) data_get($payment->metadata, 'workspace_id') !== $workspace->id) {
            abort(404);
        }

        return view('onboarding.checkout', [
            'payment' => $payment,
            'plan' => Plan::find(data_get($payment->metadata, 'plan_id')),
            'gateways' => $payment->gateway === 'checkout' ? $gateways->getEnabledGatewayNames() : [],
        ]);
    }

    public function pay(Request $request, Payment $payment, PaymentService $payments, PaymentGatewayManager $gateways): RedirectResponse
    {
        $workspace = $this->onboarding->workspaceFor($request->user());

        if ((int) data_get($payment->metadata, 'workspace_id') !== $workspace->id) {
            abort(404);
        }

        if ($payment->status === 'completed') {
            return redirect()->route('user.dashboard')->with('success', __('Your subscription is active.'));
        }

        if ($payment->gateway !== 'checkout') {
            return redirect()->route('onboarding.checkout', $payment)
                ->with('error', __('This payment is already being processed. Please complete the selected payment method.'));
        }

        $enabledGateways = $gateways->getEnabledGatewayNames();

        if ($enabledGateways === []) {
            return redirect()->route('onboarding.checkout', $payment)
                ->with('error', __('No active payment gateways are available. Please contact support.'));
        }

        $data = $request->validate([
            'gateway' => ['required', Rule::in($enabledGateways)],
        ]);

        $result = app(PlanCheckoutService::class)->pay(
            $payment,
            $data['gateway'],
            route('onboarding.payment.return', ['gateway' => $data['gateway']]),
            route('onboarding.payment.cancel'),
        );

        $gatewayPayment = $result['payment'];
        $response = $result['response'];

        if ($response->isComplete()) {
            return redirect()->route('user.dashboard')->with('success', __('Your subscription is active.'));
        }

        if ($response->isRedirect()) {
            return redirect()->away($response->redirectUrl);
        }

        return redirect()->route('onboarding.checkout', $gatewayPayment);
    }

    public function paymentReturn(Request $request, PaymentService $payments): RedirectResponse
    {
        $payment = $payments->verify($request, $request->query('gateway'));
        $workspace = $this->onboarding->workspaceFor($request->user());

        if ((int) data_get($payment->metadata, 'workspace_id') !== $workspace->id) {
            abort(404);
        }

        if ($payment->status === 'completed') {
            return redirect()->route('user.dashboard')->with('success', __('Your subscription is active.'));
        }

        return redirect()->route('onboarding.checkout', $payment)
            ->with('error', __('Payment is still pending. Please complete the payment to continue.'));
    }

    public function paymentCancel(): RedirectResponse
    {
        return redirect()->route('onboarding.plan')
            ->with('error', __('Payment was cancelled. You can choose a plan again.'));
    }

    protected function categories(): array
    {
        return [
            'ecommerce' => __('E-commerce'),
            'agency' => __('Agency'),
            'education' => __('Education'),
            'healthcare' => __('Healthcare'),
            'real_estate' => __('Real estate'),
            'services' => __('Services'),
            'other' => __('Other'),
        ];
    }

    protected function teamSizes(): array
    {
        return [
            '1' => __('Just me'),
            '2-5' => __('2-5 people'),
            '6-20' => __('6-20 people'),
            '21-50' => __('21-50 people'),
            '51+' => __('51+ people'),
        ];
    }
}
