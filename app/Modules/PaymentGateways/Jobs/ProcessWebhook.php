<?php

namespace App\Modules\PaymentGateways\Jobs;

use App\Modules\PaymentGateways\Events\PaymentFailed;
use App\Modules\PaymentGateways\Events\PaymentSucceeded;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PaymentGateways\Models\WebhookLog;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public WebhookLog $webhookLog
    ) {}

    public function handle(PaymentGatewayManager $manager): void
    {
        $driver = $manager->driver($this->webhookLog->gateway);

        // Reconstruct a request from the stored payload
        $request = Request::create('', 'POST', $this->webhookLog->payload ?? []);

        $result = $driver->handleWebhook($request);

        // Update payment status if the webhook references a payment
        if ($result->gatewayPaymentId && $result->status) {
            $payment = Payment::query()
                ->where('gateway_payment_id', $result->gatewayPaymentId)
                ->orWhere('metadata->payment_intent_id', $result->gatewayPaymentId)
                ->orWhere('metadata->checkout_session_id', $result->gatewayPaymentId)
                ->first();

            if ($payment) {
                $oldStatus = $payment->status;
                $payment->update([
                    'status' => $result->status,
                    'paid_at' => $result->status === 'completed' ? now() : $payment->paid_at,
                    'metadata' => array_merge($payment->metadata ?? [], $result->metadata),
                ]);

                if ($result->status === 'completed' && $oldStatus !== 'completed') {
                    event(new PaymentSucceeded($payment));
                } elseif ($result->status === 'failed') {
                    event(new PaymentFailed($payment));
                }
            }
        }

        $this->webhookLog->update(['processed_at' => now()]);
    }
}
