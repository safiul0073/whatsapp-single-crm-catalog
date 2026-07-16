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
 * Mollie (Europe) uses a simple redirect flow.
 *
 * createPayment() creates a payment and returns the hosted checkout URL.
 * The webhook only sends the payment id; status is always re-fetched from
 * the API, so there is no signature to verify — security comes from the
 * unguessable payment id plus the authoritative re-fetch.
 */
class MolliePaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.mollie.com/v2';

    public function name(): string
    {
        return 'mollie';
    }

    /**
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (empty(payment_gateway_setting('mollie_api_key', ''))) {
            throw new RuntimeException(
                'Mollie API key is not configured. Set it in Settings → Payment Gateways.'
            );
        }
    }

    protected function apiKey(): string
    {
        return (string) payment_gateway_setting('mollie_api_key', '');
    }

    /**
     * Mollie expects the amount as a string with two decimals plus currency.
     */
    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $reference = $data->metadata['reference'] ?? 'mol_'.uniqid();

            $response = Http::withToken($this->apiKey())
                ->post("{$this->baseUrl}/payments", array_filter([
                    'amount' => [
                        'currency' => strtoupper($data->currency),
                        'value' => number_format($data->amount, 2, '.', ''),
                    ],
                    'description' => $data->description ?: 'Payment',
                    'redirectUrl' => $data->returnUrl,
                    'cancelUrl' => $data->cancelUrl,
                    'webhookUrl' => route('webhooks.payments', ['gateway' => 'mollie']),
                    'metadata' => array_filter([
                        'reference' => $reference,
                        'user_id' => $data->userId,
                        'user_type' => $data->userType,
                    ]),
                ]));

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return PaymentResponse::failed($result['detail'] ?? 'Failed to create Mollie payment.');
            }

            $checkoutUrl = $result['_links']['checkout']['href'] ?? null;

            if (! $checkoutUrl) {
                return PaymentResponse::failed('Mollie checkout URL not found in response.');
            }

            return PaymentResponse::redirect($result['id'], $checkoutUrl, [
                'reference' => $reference,
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    /**
     * On return, Mollie provides no query params, so we re-fetch the payment
     * by its id (persisted as the gateway payment id when the payment was made).
     */
    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        $paymentId = $request->get('id')
            ?? $request->get('payment_id')
            ?? $request->get('gateway_payment_id');

        if (empty($paymentId)) {
            return PaymentResponse::failed('Missing Mollie payment id for verification.');
        }

        return $this->fetchPaymentStatus($paymentId);
    }

    protected function fetchPaymentStatus(string $paymentId): PaymentResponse
    {
        try {
            $response = Http::withToken($this->apiKey())
                ->get("{$this->baseUrl}/payments/{$paymentId}");

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return PaymentResponse::failed($result['detail'] ?? 'Mollie verification failed.');
            }

            $reference = $result['metadata']['reference'] ?? $result['id'];

            return match ($result['status'] ?? '') {
                'paid' => PaymentResponse::completed($reference, [
                    'mollie_id' => $result['id'],
                    'amount' => $result['amount']['value'] ?? null,
                    'currency' => $result['amount']['currency'] ?? null,
                    'method' => $result['method'] ?? null,
                    'paid_at' => $result['paidAt'] ?? null,
                ]),
                'open', 'pending', 'authorized' => PaymentResponse::pending($reference, [
                    'mollie_id' => $result['id'],
                    'status' => $result['status'],
                ]),
                default => PaymentResponse::failed("Mollie payment status: {$result['status']}"),
            };
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason = ''): RefundResult
    {
        $this->ensureConfigured();

        try {
            $payment = Http::withToken($this->apiKey())
                ->get("{$this->baseUrl}/payments/{$gatewayPaymentId}")
                ->json();

            $currency = $payment['amount']['currency'] ?? 'EUR';

            $response = Http::withToken($this->apiKey())
                ->post("{$this->baseUrl}/payments/{$gatewayPaymentId}/refunds", array_filter([
                    'amount' => [
                        'currency' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                    'description' => $reason ?: null,
                ]));

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return RefundResult::failed($result['detail'] ?? 'Mollie refund failed.');
            }

            return match ($result['status'] ?? '') {
                'refunded' => RefundResult::success((string) $result['id'], 'completed', [
                    'amount' => $result['amount']['value'] ?? null,
                ]),
                'pending', 'processing', 'queued' => RefundResult::pending((string) $result['id'], [
                    'amount' => $result['amount']['value'] ?? null,
                ]),
                default => RefundResult::failed("Mollie refund status: {$result['status']}"),
            };
        } catch (\Exception $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    /**
     * Mollie webhooks carry no signature — the payload is just the payment id.
     * Authenticity is established by re-fetching that id from the API, which
     * happens in handleWebhook(). We only confirm an id is present here.
     */
    public function verifyWebhook(Request $request): bool
    {
        return ! empty($request->input('id'));
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $paymentId = $request->input('id');

        if (empty($paymentId)) {
            return new WebhookResult(eventType: 'unknown');
        }

        try {
            $result = Http::withToken($this->apiKey())
                ->get("{$this->baseUrl}/payments/{$paymentId}")
                ->json();
        } catch (\Exception $e) {
            return new WebhookResult(eventType: 'payment');
        }

        if (empty($result['id'])) {
            return new WebhookResult(eventType: 'payment');
        }

        $status = match ($result['status'] ?? '') {
            'paid' => 'completed',
            'refunded' => 'refunded',
            'charged_back' => 'refunded',
            'canceled', 'expired', 'failed' => 'failed',
            'open', 'pending', 'authorized' => 'pending',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $result['metadata']['reference'] ?? $result['id'],
            status: $status,
            eventType: $result['status'] ?? 'payment',
            metadata: $result,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'gateway' => 'mollie',
        ];
    }
}
