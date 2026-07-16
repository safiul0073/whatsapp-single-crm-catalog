<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error;
use RuntimeException;

class RazorpayPaymentGateway implements PaymentGatewayInterface
{
    protected ?Api $api = null;

    public function name(): string
    {
        return 'razorpay';
    }

    /**
     * Ensure required Razorpay credentials are configured.
     *
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (! class_exists(Api::class)) {
            throw new RuntimeException(
                'Razorpay SDK is not installed. Run: composer require razorpay/razorpay'
            );
        }

        $keyId = payment_gateway_setting('razorpay_key_id', '');
        $keySecret = payment_gateway_setting('razorpay_key_secret', '');

        if (empty($keyId) || empty($keySecret)) {
            throw new RuntimeException(
                'Razorpay API keys are not configured. Set them in Settings → Payment Gateways.'
            );
        }
    }

    /**
     * Lazy-initialize the Razorpay API client.
     */
    protected function getApi(): Api
    {
        if ($this->api === null) {
            $keyId = payment_gateway_setting('razorpay_key_id', '');
            $keySecret = payment_gateway_setting('razorpay_key_secret', '');

            $this->api = new Api($keyId, $keySecret);
        }

        return $this->api;
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $keyId = payment_gateway_setting('razorpay_key_id', '');

            $order = $this->getApi()->order->create([
                'amount' => (int) round($data->amount * 100),
                'currency' => strtoupper($data->currency),
                'receipt' => $data->metadata['receipt'] ?? uniqid('rcpt_'),
                'notes' => array_filter([
                    'description' => $data->description,
                    'user_id' => $data->userId,
                    'user_type' => $data->userType,
                ]),
            ]);

            return PaymentResponse::clientAction($order->id, [
                'order_id' => $order->id,
                'key_id' => $keyId,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'name' => config('app.name'),
                'description' => $data->description ?? 'Payment',
            ]);
        } catch (Error $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $keySecret = payment_gateway_setting('razorpay_key_secret', '');

            $orderId = $request->get('razorpay_order_id');
            $paymentId = $request->get('razorpay_payment_id');
            $signature = $request->get('razorpay_signature');

            if (empty($orderId) || empty($paymentId) || empty($signature)) {
                return PaymentResponse::failed('Missing Razorpay verification parameters.');
            }

            $expectedSignature = hash_hmac(
                'sha256',
                $orderId.'|'.$paymentId,
                $keySecret
            );

            if (! hash_equals($expectedSignature, $signature)) {
                return PaymentResponse::failed('Razorpay signature verification failed.');
            }

            $payment = $this->getApi()->payment->fetch($paymentId);

            return match ($payment->status) {
                'captured' => PaymentResponse::completed($paymentId, [
                    'order_id' => $orderId,
                    'amount' => $payment->amount / 100,
                    'currency' => $payment->currency,
                    'method' => $payment->method,
                ]),
                'authorized' => PaymentResponse::completed($paymentId, [
                    'order_id' => $orderId,
                    'note' => 'Payment authorized, capture may be pending.',
                ]),
                default => PaymentResponse::failed("Razorpay payment status: {$payment->status}"),
            };
        } catch (Error $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $webhookSecret = payment_gateway_setting('razorpay_webhook_secret', '');

        if (empty($webhookSecret)) {
            return false;
        }

        $signature = $request->header('X-Razorpay-Signature', '');
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();
        $eventType = $payload['event'] ?? 'unknown';
        $entity = $payload['payload']['payment']['entity'] ?? [];

        $gatewayPaymentId = $entity['id'] ?? null;

        $status = match ($eventType) {
            'payment.authorized' => 'authorized',
            'payment.captured' => 'completed',
            'payment.failed' => 'failed',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $gatewayPaymentId,
            status: $status,
            eventType: $eventType,
            metadata: $entity,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'key_id' => payment_gateway_setting('razorpay_key_id', ''),
        ];
    }
}
