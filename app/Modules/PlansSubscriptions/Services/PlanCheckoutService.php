<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Models\User;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PaymentGateways\Services\PaymentService;
use App\Modules\PaymentGatewaySettings\Services\PaymentGatewaySettingsService;
use App\Modules\PlansSubscriptions\Enums\CheckoutIntent;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared plan checkout for onboarding and the Subscription page. The amount is always decided here,
 * on the server, from the workspace's current subscription and the target plan.
 */
class PlanCheckoutService
{
    public function __construct(
        protected SubscriptionAccessService $access,
        protected ProrationService $proration,
        protected PaymentGatewaySettingsService $gatewaySettings,
    ) {}

    public function intentFor(?Subscription $subscription, Plan $target): CheckoutIntent
    {
        $current = $subscription?->plan;

        if (! $subscription || ! $current || ! $this->access->subscriptionIsUsable($subscription) || $current->isFree()) {
            return CheckoutIntent::New;
        }

        if ($current->id === $target->id) {
            return CheckoutIntent::Renew;
        }

        return OrderMoney::minor((string) $target->price) > OrderMoney::minor((string) $current->price)
            ? CheckoutIntent::Upgrade
            : CheckoutIntent::Downgrade;
    }

    /**
     * @return array{intent: CheckoutIntent, plan_price: string, credit: string, amount_due: string}
     */
    public function quote(Workspace $workspace, Plan $target): array
    {
        $subscription = $this->access->currentForWorkspace($workspace->id);
        $intent = $this->intentFor($subscription, $target);
        $price = OrderMoney::decimal(OrderMoney::minor((string) $target->price));

        if ($intent === CheckoutIntent::Upgrade) {
            $quote = $this->proration->quote($subscription, $target);

            return ['intent' => $intent, 'plan_price' => $quote['plan_price'], 'credit' => $quote['credit'], 'amount_due' => $quote['amount_due']];
        }

        return ['intent' => $intent, 'plan_price' => $price, 'credit' => '0.00', 'amount_due' => $price];
    }

    /**
     * Create the "checkout" placeholder payment the user then pays with a gateway of their choice.
     */
    public function start(User $user, Workspace $workspace, Plan $plan, bool $onboarding = false): Payment
    {
        $subscription = $this->access->currentForWorkspace($workspace->id);

        if ($subscription?->plan?->isLifetime() && $this->access->subscriptionIsUsable($subscription)) {
            throw ValidationException::withMessages(['plan_id' => __('You already have a lifetime plan.')]);
        }

        if ($plan->isFree()) {
            throw ValidationException::withMessages(['plan_id' => __('The free plan does not need a payment.')]);
        }

        $quote = $this->quote($workspace, $plan);

        return Payment::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'user_type' => $user->getMorphClass(),
            'gateway' => 'checkout',
            'amount' => $quote['amount_due'],
            'currency' => $plan->billingCurrency(),
            'status' => 'pending',
            'description' => $this->description($plan, $quote['intent']),
            'metadata' => [
                'plan_id' => $plan->id,
                'workspace_id' => $workspace->id,
                'intent' => $quote['intent']->value,
                'plan_price' => $quote['plan_price'],
                'proration_credit' => $quote['credit'],
                'onboarding' => $onboarding,
                'checkout_only' => true,
            ],
        ]);
    }

    /**
     * Charge the checkout with the chosen gateway.
     *
     * @return array{payment: Payment, response: PaymentResponse}
     */
    public function pay(Payment $checkout, string $gateway, string $returnUrl, string $cancelUrl): array
    {
        $plan = Plan::query()->active()->findOrFail(data_get($checkout->metadata, 'plan_id'));
        $breakdown = $this->chargeBreakdown((string) $checkout->amount, $gateway);

        $checkout->update(['status' => 'processing']);

        return app(PaymentService::class)->charge((float) $breakdown['payable_amount'], $checkout->currency, [
            'gateway' => $gateway,
            'description' => $checkout->description ?: $this->description($plan, CheckoutIntent::New),
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                ...collect($checkout->metadata ?? [])->except(['checkout_only'])->all(),
                'checkout_payment_id' => $checkout->id,
                'selected_gateway' => $gateway,
                ...$breakdown,
            ],
        ]);
    }

    /**
     * Gateway fees from the gateway settings, computed in minor units.
     *
     * @return array{base_amount: string, fixed_charge: string, percent_charge: string, charge_amount: string, payable_amount: string}
     */
    public function chargeBreakdown(string|float $baseAmount, string $gateway): array
    {
        $baseMinor = OrderMoney::minor($baseAmount);
        $fixedMinor = OrderMoney::minor($this->gatewaySettings->get("{$gateway}_fixed_charge", 0) ?: 0);
        $percent = (string) ($this->gatewaySettings->get("{$gateway}_percent_charge", 0) ?: 0);
        $basisPoints = OrderMoney::minor($percent);
        $percentMinor = intdiv($baseMinor * $basisPoints + 5000, 10000);
        $chargeMinor = $fixedMinor + $percentMinor;

        return [
            'base_amount' => OrderMoney::decimal($baseMinor),
            'fixed_charge' => OrderMoney::decimal($fixedMinor),
            'percent_charge' => $percent,
            'charge_amount' => OrderMoney::decimal($chargeMinor),
            'payable_amount' => OrderMoney::decimal($baseMinor + $chargeMinor),
        ];
    }

    public function belongsTo(Payment $payment, Workspace $workspace): bool
    {
        return (int) data_get($payment->metadata, 'workspace_id') === $workspace->id;
    }

    protected function description(Plan $plan, CheckoutIntent $intent): string
    {
        return match ($intent) {
            CheckoutIntent::Renew => __(':plan renewal', ['plan' => $plan->name]),
            CheckoutIntent::Upgrade => __('Upgrade to :plan', ['plan' => $plan->name]),
            CheckoutIntent::Downgrade => __('Switch to :plan', ['plan' => $plan->name]),
            CheckoutIntent::New => __(':plan subscription', ['plan' => $plan->name]),
        };
    }
}
