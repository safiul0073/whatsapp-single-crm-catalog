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
 * BitPay cryptocurrency gateway (invoice → redirect).
 *
 * A token-based invoice is created and the customer is redirected to the
 * hosted BitPay invoice. Status is confirmed by re-fetching the invoice,
 * so webhooks carry no signature — the id is re-fetched for authenticity.
 */
class BitPayPaymentGateway implements PaymentGatewayInterface
{
    protected string $liveUrl = 'https://bitpay.com';

    protected string $testUrl = 'https://test.bitpay.com';

    protected string $apiVersion = '2.0.0';

    public function name(): string
    {
        return 'bitpay';
    }

    /**
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (empty(payment_gateway_setting('bitpay_api_token', ''))) {
            throw new RuntimeException(
                'BitPay API token is not configured. Set it in Settings → Payment Gateways.'
            );
        }
    }

    protected function baseUrl(): string
    {
        return (bool) payment_gateway_setting('bitpay_sandbox', true) ? $this->testUrl : $this->liveUrl;
    }

    protected function token(): string
    {
        return (string) payment_gateway_setting('bitpay_api_token', '');
    }

    protected function headers(): array
    {
        return [
            'X-Accept-Version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ];
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $reference = $data->metadata['reference'] ?? 'btp_'.uniqid();

            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl()}/invoices", array_filter([
                    'token' => $this->token(),
                    'price' => round($data->amount, 2),
                    'currency' => strtoupper($data->currency),
                    'orderId' => $reference,
                    'itemDesc' => $data->description ?: 'Payment',
                    'redirectURL' => $data->returnUrl,
                    'closeURL' => $data->cancelUrl,
                    'notificationURL' => route('webhooks.payments', ['gateway' => 'bitpay']),
                ]));

            $invoice = $response->json()['data'] ?? [];

            if ($response->failed() || empty($invoice['id'])) {
                return PaymentResponse::failed($response->json()['error'] ?? 'Failed to create BitPay invoice.');
            }

            $url = $invoice['url'] ?? null;

            if (! $url) {
                return PaymentResponse::failed('BitPay invoice url not found in response.');
            }

            return PaymentResponse::redirect($invoice['id'], $url, [
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
            return PaymentResponse::failed('Missing BitPay invoice id for verification.');
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl()}/invoices/{$invoiceId}", ['token' => $this->token()]);

            $invoice = $response->json()['data'] ?? [];

            if ($response->failed() || empty($invoice['id'])) {
                return PaymentResponse::failed('BitPay verification failed.');
            }

            return $this->mapInvoiceResponse($invoice);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    protected function mapInvoiceResponse(array $invoice): PaymentResponse
    {
        $reference = $invoice['orderId'] ?? $invoice['id'];

        return match ($invoice['status'] ?? '') {
            'complete', 'confirmed' => PaymentResponse::completed($reference, [
                'bitpay_id' => $invoice['id'],
                'amount' => $invoice['price'] ?? null,
                'currency' => $invoice['currency'] ?? null,
            ]),
            'paid', 'new' => PaymentResponse::pending($reference, [
                'bitpay_id' => $invoice['id'],
                'status' => $invoice['status'],
            ]),
            default => PaymentResponse::failed('BitPay invoice status: '.($invoice['status'] ?? 'unknown')),
        };
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason = ''): RefundResult
    {
        $this->ensureConfigured();

        try {
            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl()}/refunds", array_filter([
                    'token' => $this->token(),
                    'invoiceId' => $gatewayPaymentId,
                    'amount' => round($amount, 2),
                    'reason' => $reason ?: null,
                ]));

            $refund = $response->json()['data'] ?? [];

            if ($response->failed() || empty($refund['id'])) {
                return RefundResult::failed($response->json()['error'] ?? 'BitPay refund failed.');
            }

            return match ($refund['status'] ?? '') {
                'success', 'completed' => RefundResult::success((string) $refund['id'], 'completed', [
                    'amount' => $refund['amount'] ?? null,
                ]),
                'pending', 'created', 'preview' => RefundResult::pending((string) $refund['id'], [
                    'amount' => $refund['amount'] ?? null,
                ]),
                default => RefundResult::failed('BitPay refund status: '.($refund['status'] ?? 'unknown')),
            };
        } catch (\Exception $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    /**
     * BitPay IPNs are unsigned; authenticity is established by re-fetching
     * the invoice. We only confirm an invoice id is present here.
     */
    public function verifyWebhook(Request $request): bool
    {
        return ! empty($request->input('data.id')) || ! empty($request->input('id'));
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $invoice = $request->input('data', $request->all());

        $status = match ($invoice['status'] ?? '') {
            'complete', 'confirmed' => 'completed',
            'paid', 'new' => 'pending',
            'expired', 'invalid' => 'failed',
            'refunded' => 'refunded',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $invoice['orderId'] ?? ($invoice['id'] ?? null),
            status: $status,
            eventType: $invoice['status'] ?? 'invoice',
            metadata: $invoice,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'gateway' => 'bitpay',
        ];
    }
}
