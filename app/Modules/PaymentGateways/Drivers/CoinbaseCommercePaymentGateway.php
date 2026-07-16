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

/**
 * Coinbase Commerce cryptocurrency gateway (hosted charge → redirect).
 *
 * Auth uses the X-CC-Api-Key header. Webhooks are signed with HMAC-SHA256
 * over the raw request body in the X-CC-Webhook-Signature header.
 */
class CoinbaseCommercePaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.commerce.coinbase.com';

    protected string $apiVersion = '2018-03-22';

    public function name(): string
    {
        return 'coinbasecommerce';
    }

    /**
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (empty(payment_gateway_setting('coinbasecommerce_api_key', ''))) {
            throw new RuntimeException(
                'Coinbase Commerce API key is not configured. Set it in Settings → Payment Gateways.'
            );
        }
    }

    protected function headers(): array
    {
        return [
            'X-CC-Api-Key' => (string) payment_gateway_setting('coinbasecommerce_api_key', ''),
            'X-CC-Version' => $this->apiVersion,
        ];
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $reference = $data->metadata['reference'] ?? 'cbc_'.uniqid();

            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/charges", [
                    'name' => $data->description ?: 'Payment',
                    'description' => $data->description ?: 'Payment',
                    'pricing_type' => 'fixed_price',
                    'local_price' => [
                        'amount' => number_format($data->amount, 2, '.', ''),
                        'currency' => strtoupper($data->currency),
                    ],
                    'metadata' => array_filter([
                        'reference' => $reference,
                        'user_id' => $data->userId,
                        'user_type' => $data->userType,
                    ]),
                    'redirect_url' => $data->returnUrl,
                    'cancel_url' => $data->cancelUrl,
                ]);

            $result = $response->json();
            $charge = $result['data'] ?? [];

            if ($response->failed() || empty($charge['id'])) {
                return PaymentResponse::failed($result['error']['message'] ?? 'Failed to create Coinbase Commerce charge.');
            }

            $hostedUrl = $charge['hosted_url'] ?? null;

            if (! $hostedUrl) {
                return PaymentResponse::failed('Coinbase Commerce hosted_url not found in response.');
            }

            return PaymentResponse::redirect($charge['id'], $hostedUrl, [
                'reference' => $reference,
                'code' => $charge['code'] ?? null,
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        $chargeId = $request->get('id')
            ?? $request->get('charge_id')
            ?? $request->get('gateway_payment_id');

        if (empty($chargeId)) {
            return PaymentResponse::failed('Missing Coinbase Commerce charge id for verification.');
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/charges/{$chargeId}");

            $charge = $response->json()['data'] ?? [];

            if ($response->failed() || empty($charge['id'])) {
                return PaymentResponse::failed('Coinbase Commerce verification failed.');
            }

            return $this->mapChargeResponse($charge);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    /**
     * The latest timeline entry holds the current charge status.
     */
    protected function mapChargeResponse(array $charge): PaymentResponse
    {
        $reference = $charge['metadata']['reference'] ?? $charge['id'];
        $timeline = $charge['timeline'] ?? [];
        $latest = end($timeline)['status'] ?? '';

        return match ($latest) {
            'COMPLETED', 'RESOLVED' => PaymentResponse::completed($reference, [
                'coinbase_id' => $charge['id'],
                'code' => $charge['code'] ?? null,
            ]),
            'NEW', 'PENDING' => PaymentResponse::pending($reference, [
                'coinbase_id' => $charge['id'],
                'status' => $latest,
            ]),
            default => PaymentResponse::failed("Coinbase Commerce charge status: {$latest}"),
        };
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason = ''): RefundResult
    {
        // Coinbase Commerce has no refund API; crypto refunds are manual.
        return RefundResult::failed('Coinbase Commerce does not support automated refunds.');
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) payment_gateway_setting('coinbasecommerce_webhook_secret', '');
        $signature = (string) $request->header('X-CC-Webhook-Signature', '');

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $event = $request->input('event', []);
        $eventType = $event['type'] ?? 'unknown';
        $charge = $event['data'] ?? [];

        $status = match ($eventType) {
            'charge:confirmed', 'charge:resolved' => 'completed',
            'charge:failed' => 'failed',
            'charge:pending', 'charge:created' => 'pending',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $charge['metadata']['reference'] ?? ($charge['id'] ?? null),
            status: $status,
            eventType: $eventType,
            metadata: $charge,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'gateway' => 'coinbasecommerce',
        ];
    }
}
