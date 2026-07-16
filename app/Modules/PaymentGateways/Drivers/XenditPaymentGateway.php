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
 * Xendit (Southeast Asia) uses a redirect flow via the Invoice API.
 *
 * Auth is HTTP Basic with the secret key as the username and empty password.
 * Webhooks are verified with a static callback token (x-callback-token).
 */
class XenditPaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.xendit.co';

    public function name(): string
    {
        return 'xendit';
    }

    /**
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (empty(payment_gateway_setting('xendit_secret_key', ''))) {
            throw new RuntimeException(
                'Xendit secret key is not configured. Set it in Settings → Payment Gateways.'
            );
        }
    }

    protected function secretKey(): string
    {
        return (string) payment_gateway_setting('xendit_secret_key', '');
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $reference = $data->metadata['reference'] ?? 'xnd_'.uniqid();

            $response = Http::withBasicAuth($this->secretKey(), '')
                ->post("{$this->baseUrl}/v2/invoices", array_filter([
                    'external_id' => $reference,
                    'amount' => round($data->amount, 2),
                    'currency' => strtoupper($data->currency),
                    'description' => $data->description ?: 'Payment',
                    'payer_email' => $data->metadata['email'] ?? null,
                    'success_redirect_url' => $data->returnUrl,
                    'failure_redirect_url' => $data->cancelUrl,
                ]));

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return PaymentResponse::failed($result['message'] ?? 'Failed to create Xendit invoice.');
            }

            $invoiceUrl = $result['invoice_url'] ?? null;

            if (! $invoiceUrl) {
                return PaymentResponse::failed('Xendit invoice_url not found in response.');
            }

            return PaymentResponse::redirect($result['id'], $invoiceUrl, [
                'reference' => $reference,
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        $invoiceId = $request->get('id')
            ?? $request->get('invoice_id')
            ?? $request->get('gateway_payment_id');

        if (empty($invoiceId)) {
            return PaymentResponse::failed('Missing Xendit invoice id for verification.');
        }

        try {
            $response = Http::withBasicAuth($this->secretKey(), '')
                ->get("{$this->baseUrl}/v2/invoices/{$invoiceId}");

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return PaymentResponse::failed($result['message'] ?? 'Xendit verification failed.');
            }

            $reference = $result['external_id'] ?? $result['id'];

            return match ($result['status'] ?? '') {
                'PAID', 'SETTLED' => PaymentResponse::completed($reference, [
                    'xendit_id' => $result['id'],
                    'amount' => $result['amount'] ?? null,
                    'currency' => $result['currency'] ?? null,
                    'paid_at' => $result['paid_at'] ?? null,
                ]),
                'PENDING' => PaymentResponse::pending($reference, [
                    'xendit_id' => $result['id'],
                    'status' => $result['status'],
                ]),
                default => PaymentResponse::failed("Xendit invoice status: {$result['status']}"),
            };
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason = ''): RefundResult
    {
        $this->ensureConfigured();

        try {
            $response = Http::withBasicAuth($this->secretKey(), '')
                ->post("{$this->baseUrl}/refunds", array_filter([
                    'invoice_id' => $gatewayPaymentId,
                    'amount' => round($amount, 2),
                    'reason' => $reason ?: 'REQUESTED_BY_CUSTOMER',
                ]));

            $result = $response->json();

            if ($response->failed() || empty($result['id'])) {
                return RefundResult::failed($result['message'] ?? 'Xendit refund failed.');
            }

            return match ($result['status'] ?? '') {
                'SUCCEEDED' => RefundResult::success((string) $result['id'], 'completed', [
                    'amount' => $result['amount'] ?? null,
                ]),
                'PENDING' => RefundResult::pending((string) $result['id'], [
                    'amount' => $result['amount'] ?? null,
                ]),
                default => RefundResult::failed("Xendit refund status: {$result['status']}"),
            };
        } catch (\Exception $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $token = (string) payment_gateway_setting('xendit_webhook_token', '');
        $received = (string) $request->header('x-callback-token', '');

        if (empty($token) || empty($received)) {
            return false;
        }

        return hash_equals($token, $received);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();

        $status = match ($payload['status'] ?? '') {
            'PAID', 'SETTLED' => 'completed',
            'EXPIRED' => 'failed',
            'PENDING' => 'pending',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $payload['external_id'] ?? ($payload['id'] ?? null),
            status: $status,
            eventType: $payload['status'] ?? 'invoice',
            metadata: $payload,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'gateway' => 'xendit',
        ];
    }
}
