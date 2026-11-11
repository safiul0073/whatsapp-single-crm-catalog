<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Http\Controllers\User\SubscriptionController;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\Workspaces\Models\Workspace;

/**
 * View data for a printable plan invoice / receipt.
 */
class InvoicePresenter
{
    public function __construct(protected InvoiceNumberService $numbers) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Payment $payment): array
    {
        $metadata = $payment->metadata ?? [];
        $workspace = Workspace::query()->with('owner')->find(data_get($metadata, 'workspace_id'));
        $plan = Plan::query()->find(data_get($metadata, 'plan_id'));
        $currency = $payment->currency ?: currency_default_code();
        $money = fn (mixed $amount): string => currency_format((float) $amount, $currency, true);
        $credit = (float) data_get($metadata, 'proration_credit', 0);
        $fee = (float) data_get($metadata, 'charge_amount', 0);

        $lines = [[
            'description' => $plan
                ? __(':plan plan (:interval)', ['plan' => $plan->name, 'interval' => $plan->isLifetime() ? __('lifetime') : __('per :interval', ['interval' => $plan->interval])])
                : ($payment->description ?: __('Subscription')),
            'amount' => $money(data_get($metadata, 'plan_price', data_get($metadata, 'base_amount', $payment->amount))),
        ]];

        if ($credit > 0) {
            $lines[] = ['description' => __('Credit for unused time on previous plan'), 'amount' => '−'.$money($credit)];
        }

        if ($fee > 0) {
            $lines[] = ['description' => __('Payment processing fee'), 'amount' => $money($fee)];
        }

        $logo = setting('site_logo');

        return [
            'number' => $this->numbers->assign($payment),
            'date' => ($payment->paid_at ?? $payment->created_at)?->format('M j, Y'),
            'status' => __(str($payment->status)->headline()->toString()),
            'reference' => $payment->gateway_payment_id ?: $payment->uuid,
            'gateway' => SubscriptionController::gatewayLabel((string) data_get($metadata, 'selected_gateway', $payment->gateway)),
            'brand' => [
                'name' => (string) setting('site_name', config('app.name')),
                'logo' => $logo ? media_url($logo) : null,
            ],
            'bill_to' => [
                'workspace' => $workspace?->name,
                'name' => $workspace?->owner?->name,
                'email' => $workspace?->owner?->email,
            ],
            'lines' => $lines,
            'total' => $money($payment->amount),
            'back_url' => route('user.subscription.show'),
        ];
    }
}
