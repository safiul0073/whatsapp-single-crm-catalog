<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'log';
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $id = 'log_'.Str::uuid();

        Log::channel('single')->info('Payment created', [
            'gateway_payment_id' => $id,
            'amount' => $data->amount,
            'currency' => $data->currency,
            'description' => $data->description,
        ]);

        return PaymentResponse::completed($id, [
            'driver' => 'log',
            'note' => 'Auto-completed by log driver for development.',
        ]);
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $id = $request->get('gateway_payment_id', 'log_'.Str::uuid());

        return PaymentResponse::completed($id);
    }

    public function verifyWebhook(Request $request): bool
    {
        return true;
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        return new WebhookResult(
            gatewayPaymentId: $request->get('gateway_payment_id'),
            status: 'completed',
            eventType: 'payment.completed',
        );
    }

    public function getClientConfig(): array
    {
        return [
            'gateway' => 'log',
            'mode' => 'development',
        ];
    }
}
