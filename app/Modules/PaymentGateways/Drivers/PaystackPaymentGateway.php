<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackPaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.paystack.co';

    public function name(): string
    {
        return 'paystack';
    }

    /**
     * Ensure required Paystack credentials are configured.
     *
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        $secretKey = payment_gateway_setting('paystack_secret_key', '');
        $publicKey = payment_gateway_setting('paystack_public_key', '');

        if (empty($secretKey) || empty($publicKey)) {
            throw new RuntimeException(
                'Paystack API keys are not configured. Set them in Settings → Payment Gateways.'
            );
        }
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $secretKey = payment_gateway_setting('paystack_secret_key', '');
            $reference = $data->metadata['reference'] ?? 'pstk_'.uniqid();

            $response = Http::withToken($secretKey)
                ->post("{$this->baseUrl}/transaction/initialize", array_filter([
                    'email' => $data->metadata['email'] ?? null,
                    'amount' => (int) round($data->amount * 100),
                    'currency' => strtoupper($data->currency),
                    'reference' => $reference,
                    'callback_url' => $data->returnUrl,
                    'metadata' => array_filter([
                        'user_id' => $data->userId,
                        'user_type' => $data->userType,
                        'description' => $data->description,
                        'cancel_url' => $data->cancelUrl,
                    ]),
                ]));

            $result = $response->json();

            if (! ($result['status'] ?? false)) {
                return PaymentResponse::failed($result['message'] ?? 'Failed to initialize Paystack transaction.');
            }

            $authorizationUrl = $result['data']['authorization_url'] ?? null;

            if (! $authorizationUrl) {
                return PaymentResponse::failed('Paystack authorization URL not found in response.');
            }

            return PaymentResponse::redirect($reference, $authorizationUrl, [
                'access_code' => $result['data']['access_code'] ?? null,
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $secretKey = payment_gateway_setting('paystack_secret_key', '');
            $reference = $request->get('reference') ?? $request->get('trxref');

            if (empty($reference)) {
                return PaymentResponse::failed('Missing reference parameter from Paystack redirect.');
            }

            $response = Http::withToken($secretKey)
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");

            $result = $response->json();

            if (! ($result['status'] ?? false)) {
                return PaymentResponse::failed($result['message'] ?? 'Paystack verification failed.');
            }

            $data = $result['data'] ?? [];
            $gatewayStatus = $data['status'] ?? '';

            if ($gatewayStatus === 'success') {
                return PaymentResponse::completed($reference, [
                    'paystack_id' => $data['id'] ?? null,
                    'amount' => ($data['amount'] ?? 0) / 100,
                    'currency' => $data['currency'] ?? null,
                    'channel' => $data['channel'] ?? null,
                    'paid_at' => $data['paid_at'] ?? null,
                ]);
            }

            return PaymentResponse::failed("Paystack transaction status: {$gatewayStatus}");
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $secretKey = payment_gateway_setting('paystack_secret_key', '');
        $signature = $request->header('X-Paystack-Signature', '');

        if (empty($signature) || empty($secretKey)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha512', $request->getContent(), $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();
        $eventType = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        $gatewayPaymentId = $data['reference'] ?? ($data['id'] ?? null);

        $status = match ($eventType) {
            'charge.success' => 'completed',
            'transfer.success' => 'completed',
            'transfer.failed' => 'failed',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $gatewayPaymentId ? (string) $gatewayPaymentId : null,
            status: $status,
            eventType: $eventType,
            metadata: $data,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'public_key' => payment_gateway_setting('paystack_public_key', ''),
        ];
    }
}
