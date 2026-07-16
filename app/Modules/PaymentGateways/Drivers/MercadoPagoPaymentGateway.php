<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\RefundResult;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MercadoPagoPaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.mercadopago.com';

    public function name(): string
    {
        return 'mercadopago';
    }

    /**
     * Ensure required Mercado Pago credentials are configured.
     *
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (empty(payment_gateway_setting('mercadopago_access_token', ''))) {
            throw new RuntimeException(
                'Mercado Pago access token is not configured. Set it in Settings → Payment Gateways.'
            );
        }
    }

    protected function accessToken(): string
    {
        return (string) payment_gateway_setting('mercadopago_access_token', '');
    }

    /**
     * Create a Checkout Pro preference and return its redirect URL.
     */
    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $reference = $data->metadata['reference'] ?? 'mp_'.uniqid();
            $sandbox = (bool) payment_gateway_setting('mercadopago_sandbox', true);

            $response = Http::withToken($this->accessToken())
                ->post("{$this->baseUrl}/checkout/preferences", array_filter([
                    'items' => [[
                        'title' => $data->description ?: 'Payment',
                        'quantity' => 1,
                        'currency_id' => strtoupper($data->currency),
                        'unit_price' => round($data->amount, 2),
                    ]],
                    'external_reference' => $reference,
                    'back_urls' => array_filter([
                        'success' => $data->returnUrl,
                        'pending' => $data->returnUrl,
                        'failure' => $data->cancelUrl,
                    ]),
                    'auto_return' => $data->returnUrl ? 'approved' : null,
                    'notification_url' => route('webhooks.payments', ['gateway' => 'mercadopago']),
                    'metadata' => array_filter([
                        'user_id' => $data->userId,
                        'user_type' => $data->userType,
                    ]),
                ]));

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return PaymentResponse::failed($result['message'] ?? 'Failed to create Mercado Pago preference.');
            }

            $redirectUrl = $sandbox
                ? ($result['sandbox_init_point'] ?? $result['init_point'] ?? null)
                : ($result['init_point'] ?? null);

            if (! $redirectUrl) {
                return PaymentResponse::failed('Mercado Pago init_point not found in response.');
            }

            return PaymentResponse::redirect($reference, $redirectUrl, [
                'preference_id' => $result['id'],
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    /**
     * Verify a payment after the user returns from Checkout Pro.
     *
     * Mercado Pago appends payment_id/collection_id to the return URL.
     */
    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $paymentId = $request->get('payment_id')
                ?? $request->get('collection_id')
                ?? $request->get('data_id');

            if (empty($paymentId)) {
                return PaymentResponse::failed('Missing payment identifier from Mercado Pago redirect.');
            }

            $response = Http::withToken($this->accessToken())
                ->get("{$this->baseUrl}/v1/payments/{$paymentId}");

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return PaymentResponse::failed($result['message'] ?? 'Mercado Pago verification failed.');
            }

            $reference = $result['external_reference'] ?? (string) $result['id'];

            return match ($result['status'] ?? '') {
                'approved' => PaymentResponse::completed($reference, [
                    'mercadopago_id' => $result['id'],
                    'amount' => $result['transaction_amount'] ?? null,
                    'currency' => $result['currency_id'] ?? null,
                    'status_detail' => $result['status_detail'] ?? null,
                ]),
                'pending', 'in_process', 'authorized' => PaymentResponse::pending($reference, [
                    'mercadopago_id' => $result['id'],
                    'status' => $result['status'],
                ]),
                default => PaymentResponse::failed("Mercado Pago payment status: {$result['status']}"),
            };
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason = ''): RefundResult
    {
        $this->ensureConfigured();

        try {
            $response = Http::withToken($this->accessToken())
                ->post("{$this->baseUrl}/v1/payments/{$gatewayPaymentId}/refunds", [
                    'amount' => round($amount, 2),
                ]);

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return RefundResult::failed($result['message'] ?? 'Mercado Pago refund failed.');
            }

            return match ($result['status'] ?? '') {
                'approved' => RefundResult::success((string) $result['id'], 'completed', [
                    'amount' => $result['amount'] ?? null,
                ]),
                'in_process', 'pending' => RefundResult::pending((string) $result['id'], [
                    'amount' => $result['amount'] ?? null,
                ]),
                default => RefundResult::failed("Mercado Pago refund status: {$result['status']}"),
            };
        } catch (\Exception $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    /**
     * Verify the x-signature header per Mercado Pago's manifest scheme.
     *
     * Manifest: id:{data.id};request-id:{x-request-id};ts:{ts};
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) payment_gateway_setting('mercadopago_webhook_secret', '');
        $signature = $request->header('x-signature', '');

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $parts = collect(explode(',', $signature))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

                return [trim($key) => trim($value)];
            });

        $ts = $parts->get('ts');
        $hash = $parts->get('v1');

        if (empty($ts) || empty($hash)) {
            return false;
        }

        $dataId = $request->query('data.id') ?? $request->input('data.id') ?? '';
        $requestId = $request->header('x-request-id', '');

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $hash);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $type = $request->input('type') ?? $request->input('topic') ?? 'unknown';
        $paymentId = $request->input('data.id') ?? $request->query('data.id');

        if ($type !== 'payment' || empty($paymentId)) {
            return new WebhookResult(eventType: $type);
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->get("{$this->baseUrl}/v1/payments/{$paymentId}");
            $payment = $response->json();
        } catch (\Exception $e) {
            return new WebhookResult(eventType: $type);
        }

        $status = match ($payment['status'] ?? '') {
            'approved' => 'completed',
            'refunded' => 'refunded',
            'charged_back' => 'refunded',
            'cancelled', 'rejected' => 'failed',
            'pending', 'in_process', 'authorized' => 'pending',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $payment['external_reference'] ?? (isset($payment['id']) ? (string) $payment['id'] : null),
            status: $status,
            eventType: $type,
            metadata: $payment ?? [],
        );
    }

    public function getClientConfig(): array
    {
        return [
            'public_key' => payment_gateway_setting('mercadopago_public_key', ''),
            'sandbox' => (bool) payment_gateway_setting('mercadopago_sandbox', true),
        ];
    }
}
