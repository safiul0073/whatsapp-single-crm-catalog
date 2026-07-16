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
 * NOWPayments is a non-custodial cryptocurrency processor.
 *
 * createPayment() creates a hosted invoice and redirects the customer to it.
 * IPN webhooks are signed with HMAC-SHA512 over the ksort()-ed JSON payload,
 * sent in the x-nowpayments-sig header.
 */
class NowPaymentsPaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.nowpayments.io/v1';

    public function name(): string
    {
        return 'nowpayments';
    }

    /**
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (empty(payment_gateway_setting('nowpayments_api_key', ''))) {
            throw new RuntimeException(
                'NOWPayments API key is not configured. Set it in Settings → Payment Gateways.'
            );
        }
    }

    protected function apiKey(): string
    {
        return (string) payment_gateway_setting('nowpayments_api_key', '');
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $reference = $data->metadata['reference'] ?? 'now_'.uniqid();

            $response = Http::withHeaders(['x-api-key' => $this->apiKey()])
                ->post("{$this->baseUrl}/invoice", array_filter([
                    'price_amount' => round($data->amount, 2),
                    'price_currency' => strtolower($data->currency),
                    'order_id' => $reference,
                    'order_description' => $data->description ?: 'Payment',
                    'ipn_callback_url' => route('webhooks.payments', ['gateway' => 'nowpayments']),
                    'success_url' => $data->returnUrl,
                    'cancel_url' => $data->cancelUrl,
                ]));

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return PaymentResponse::failed($result['message'] ?? 'Failed to create NOWPayments invoice.');
            }

            $invoiceUrl = $result['invoice_url'] ?? null;

            if (! $invoiceUrl) {
                return PaymentResponse::failed('NOWPayments invoice_url not found in response.');
            }

            return PaymentResponse::redirect((string) $result['id'], $invoiceUrl, [
                'reference' => $reference,
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        $paymentId = $request->get('payment_id')
            ?? $request->get('NP_id')
            ?? $request->get('id');

        if (empty($paymentId)) {
            return PaymentResponse::failed('Missing NOWPayments payment id for verification.');
        }

        try {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey()])
                ->get("{$this->baseUrl}/payment/{$paymentId}");

            $result = $response->json();

            if ($response->failed() || empty($result['payment_id'])) {
                return PaymentResponse::failed($result['message'] ?? 'NOWPayments verification failed.');
            }

            return $this->mapPaymentResponse($result);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    protected function mapPaymentResponse(array $result): PaymentResponse
    {
        $reference = $result['order_id'] ?? (string) ($result['payment_id'] ?? '');

        return match ($result['payment_status'] ?? '') {
            'finished', 'confirmed' => PaymentResponse::completed($reference, [
                'nowpayments_id' => $result['payment_id'] ?? null,
                'pay_currency' => $result['pay_currency'] ?? null,
                'actually_paid' => $result['actually_paid'] ?? null,
            ]),
            'waiting', 'confirming', 'sending', 'partially_paid' => PaymentResponse::pending($reference, [
                'nowpayments_id' => $result['payment_id'] ?? null,
                'status' => $result['payment_status'],
            ]),
            default => PaymentResponse::failed('NOWPayments payment status: '.($result['payment_status'] ?? 'unknown')),
        };
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason = ''): RefundResult
    {
        // NOWPayments does not support programmatic refunds via the merchant API;
        // crypto refunds are handled manually from the dashboard.
        return RefundResult::failed('NOWPayments does not support automated refunds.');
    }

    public function verifyWebhook(Request $request): bool
    {
        $ipnSecret = (string) payment_gateway_setting('nowpayments_ipn_secret', '');
        $signature = (string) $request->header('x-nowpayments-sig', '');

        if (empty($ipnSecret) || empty($signature)) {
            return false;
        }

        $payload = $request->all();
        ksort($payload);
        $sorted = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $expected = hash_hmac('sha512', $sorted, $ipnSecret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();
        $paymentStatus = $payload['payment_status'] ?? '';

        $status = match ($paymentStatus) {
            'finished', 'confirmed' => 'completed',
            'waiting', 'confirming', 'sending', 'partially_paid' => 'pending',
            'refunded' => 'refunded',
            'failed', 'expired' => 'failed',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $payload['order_id'] ?? (isset($payload['payment_id']) ? (string) $payload['payment_id'] : null),
            status: $status,
            eventType: $paymentStatus ?: 'payment',
            metadata: $payload,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'gateway' => 'nowpayments',
        ];
    }
}
