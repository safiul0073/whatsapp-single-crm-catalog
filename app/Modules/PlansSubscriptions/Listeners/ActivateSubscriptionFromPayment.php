<?php

namespace App\Modules\PlansSubscriptions\Listeners;

use App\Modules\PaymentGateways\Events\PaymentSucceeded;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Services\InvoiceNumberService;
use App\Modules\PlansSubscriptions\Services\SubscriptionManager;

/**
 * Turns any completed plan payment (return URL, webhook, or admin approval) into an active subscription.
 */
class ActivateSubscriptionFromPayment
{
    public function __construct(
        protected SubscriptionManager $subscriptions,
        protected InvoiceNumberService $invoices,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment->fresh() ?? $event->payment;

        if (! data_get($payment->metadata, 'plan_id') || ! data_get($payment->metadata, 'workspace_id')) {
            return;
        }

        $this->subscriptions->applyPayment($payment);
        $this->invoices->assign($payment);
        $this->linkCheckoutPayment($payment);
    }

    /**
     * The "checkout" placeholder payment created before a gateway was picked points at the real payment.
     */
    protected function linkCheckoutPayment(Payment $payment): void
    {
        $checkoutId = data_get($payment->metadata, 'checkout_payment_id');

        if (! $checkoutId || (int) $checkoutId === $payment->id) {
            return;
        }

        $checkout = Payment::query()->where('gateway', 'checkout')->find($checkoutId);

        if (! $checkout || data_get($checkout->metadata, 'paid_payment_id')) {
            return;
        }

        $checkout->update([
            'status' => 'superseded',
            'metadata' => array_merge($checkout->metadata ?? [], ['paid_payment_id' => $payment->id]),
        ]);
    }
}
