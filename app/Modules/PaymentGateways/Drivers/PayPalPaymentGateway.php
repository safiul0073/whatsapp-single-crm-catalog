<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Blendbyte\PayPal\Services\PayPal;
use Illuminate\Http\Request;
use RuntimeException;

class PayPalPaymentGateway implements PaymentGatewayInterface
{
    protected ?PayPal $provider = null;

    public function name(): string
    {
        return 'paypal';
    }

    /**
     * Ensure required PayPal credentials are configured.
     *
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (! class_exists(PayPal::class)) {
            throw new RuntimeException(
                'PayPal SDK is not installed. Run: composer require blendbyte/paypal'
            );
        }

        $clientId = payment_gateway_setting('paypal_client_id', '');
        $clientSecret = payment_gateway_setting('paypal_client_secret', '');

        if (empty($clientId) || empty($clientSecret)) {
            throw new RuntimeException(
                'PayPal credentials are not configured. Set them in Settings → Payment Gateways.'
            );
        }
    }

    /**
     * Lazy-initialize the PayPal provider.
     */
    protected function getProvider(): PayPal
    {
        if ($this->provider === null) {
            $clientId = payment_gateway_setting('paypal_client_id', '');
            $clientSecret = payment_gateway_setting('paypal_client_secret', '');
            $sandbox = (bool) payment_gateway_setting('paypal_sandbox', true);

            $this->provider = new PayPal;
            $this->provider->setApiCredentials([
                'mode' => $sandbox ? 'sandbox' : 'live',
                'sandbox' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
                'live' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
            ]);
            $this->provider->getAccessToken();
        }

        return $this->provider;
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $order = $this->getProvider()->createOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => strtoupper($data->currency),
                            'value' => number_format($data->amount, 2, '.', ''),
                        ],
                        'description' => $data->description ?? 'Payment',
                    ],
                ],
                'application_context' => array_filter([
                    'return_url' => $data->returnUrl,
                    'cancel_url' => $data->cancelUrl,
                ]),
            ]);

            if (isset($order['error'])) {
                return PaymentResponse::failed($order['error']['message'] ?? 'Failed to create PayPal order.');
            }

            $approvalUrl = collect($order['links'] ?? [])
                ->firstWhere('rel', 'approve')['href'] ?? null;

            if (! $approvalUrl) {
                return PaymentResponse::failed('PayPal approval URL not found in response.');
            }

            return PaymentResponse::redirect($order['id'], $approvalUrl, [
                'order_status' => $order['status'] ?? null,
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $token = $request->get('token');

            if (empty($token)) {
                return PaymentResponse::failed('Missing token parameter from PayPal redirect.');
            }

            $result = $this->getProvider()->capturePaymentOrder($token);

            if (isset($result['error'])) {
                return PaymentResponse::failed($result['error']['message'] ?? 'Failed to capture PayPal order.');
            }

            $status = $result['status'] ?? '';
            $captureId = $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? $token;

            if ($status === 'COMPLETED') {
                return PaymentResponse::completed($captureId, [
                    'order_id' => $token,
                    'payer' => $result['payer'] ?? [],
                ]);
            }

            return PaymentResponse::failed("PayPal order status: {$status}");
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        try {
            $result = $this->getProvider()->verifyWebHook([
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => payment_gateway_setting('paypal_webhook_id', ''),
                'webhook_event' => $request->all(),
            ]);

            return ($result['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Exception) {
            return false;
        }
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();
        $eventType = $payload['event_type'] ?? 'unknown';
        $resource = $payload['resource'] ?? [];

        $status = match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => 'completed',
            'PAYMENT.CAPTURE.DENIED' => 'failed',
            'PAYMENT.CAPTURE.PENDING' => 'pending',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $resource['id'] ?? null,
            status: $status,
            eventType: $eventType,
            metadata: $resource,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'client_id' => payment_gateway_setting('paypal_client_id', ''),
            'sandbox' => (bool) payment_gateway_setting('paypal_sandbox', true),
        ];
    }
}
