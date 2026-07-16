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
 * Izipay (Peru) runs on the Lyra / PayZen REST platform.
 *
 * Flow is embedded: createPayment() requests a FormToken which the frontend
 * JS (KR SDK) uses to render the payment form. The browser POSTs a kr-answer
 * back, signed with kr-hash (HMAC-SHA256), which verifyPayment() validates.
 */
class IzipayPaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.micuentaweb.pe';

    public function name(): string
    {
        return 'izipay';
    }

    /**
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        $shopId = payment_gateway_setting('izipay_shop_id', '');
        $password = payment_gateway_setting('izipay_api_password', '');

        if (empty($shopId) || empty($password)) {
            throw new RuntimeException(
                'Izipay credentials are not configured. Set them in Settings → Payment Gateways.'
            );
        }
    }

    protected function authHeader(): string
    {
        $shopId = (string) payment_gateway_setting('izipay_shop_id', '');
        $password = (string) payment_gateway_setting('izipay_api_password', '');

        return 'Basic '.base64_encode("{$shopId}:{$password}");
    }

    /**
     * Izipay expects the smallest currency unit (e.g. cents).
     */
    protected function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Request a FormToken for the embedded payment form.
     */
    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $reference = $data->metadata['reference'] ?? 'izi_'.uniqid();

            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->post("{$this->baseUrl}/api-payment/V4/Charge/CreatePayment", array_filter([
                    'amount' => $this->toMinorUnits($data->amount),
                    'currency' => strtoupper($data->currency),
                    'orderId' => $reference,
                    'customer' => array_filter([
                        'email' => $data->metadata['email'] ?? null,
                        'reference' => $data->userId,
                    ]) ?: null,
                ]));

            $result = $response->json();

            if (($result['status'] ?? '') !== 'SUCCESS') {
                $message = $result['answer']['errorMessage'] ?? 'Failed to create Izipay payment.';

                return PaymentResponse::failed($message);
            }

            $formToken = $result['answer']['formToken'] ?? null;

            if (! $formToken) {
                return PaymentResponse::failed('Izipay formToken not found in response.');
            }

            return PaymentResponse::clientAction($reference, [
                'form_token' => $formToken,
                'public_key' => payment_gateway_setting('izipay_public_key', ''),
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    /**
     * Validate the kr-answer returned by the browser using the kr-hash signature.
     */
    public function verifyPayment(Request $request): PaymentResponse
    {
        try {
            $krAnswer = $request->input('kr-answer');
            $krHash = $request->input('kr-hash');

            if (empty($krAnswer) || empty($krHash)) {
                return PaymentResponse::failed('Missing kr-answer or kr-hash from Izipay return.');
            }

            if (! $this->isValidSignature($krAnswer, $krHash)) {
                return PaymentResponse::failed('Izipay signature verification failed.');
            }

            $answer = json_decode($krAnswer, true) ?: [];
            $reference = $answer['orderDetails']['orderId'] ?? null;
            $transaction = $answer['transactions'][0] ?? [];

            return match ($answer['orderStatus'] ?? '') {
                'PAID' => PaymentResponse::completed((string) ($reference ?? $transaction['uuid'] ?? ''), [
                    'izipay_uuid' => $transaction['uuid'] ?? null,
                    'amount' => isset($transaction['amount']) ? $transaction['amount'] / 100 : null,
                    'currency' => $transaction['currency'] ?? null,
                ]),
                'RUNNING', 'PARTIALLY_PAID' => PaymentResponse::pending((string) ($reference ?? ''), [
                    'order_status' => $answer['orderStatus'],
                ]),
                default => PaymentResponse::failed("Izipay order status: {$answer['orderStatus']}"),
            };
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason = ''): RefundResult
    {
        $this->ensureConfigured();

        try {
            $response = Http::withHeaders(['Authorization' => $this->authHeader()])
                ->post("{$this->baseUrl}/api-payment/V4/Transaction/CancelOrRefund", [
                    'uuid' => $gatewayPaymentId,
                    'amount' => $this->toMinorUnits($amount),
                    'resolutionMode' => 'AUTO',
                ]);

            $result = $response->json();

            if (($result['status'] ?? '') !== 'SUCCESS') {
                $message = $result['answer']['errorMessage'] ?? 'Izipay refund failed.';

                return RefundResult::failed($message);
            }

            $transaction = $result['answer'] ?? [];
            $detailedStatus = $transaction['detailedStatus'] ?? $transaction['status'] ?? '';

            return match ($detailedStatus) {
                'CANCELLED', 'REFUNDED', 'ACCEPTED' => RefundResult::success(
                    (string) ($transaction['uuid'] ?? $gatewayPaymentId),
                    'completed',
                    ['amount' => isset($transaction['amount']) ? $transaction['amount'] / 100 : null],
                ),
                'WAITING_AUTHORISATION', 'RUNNING' => RefundResult::pending(
                    (string) ($transaction['uuid'] ?? $gatewayPaymentId),
                ),
                default => RefundResult::failed('Izipay refund status: '.($transaction['status'] ?? 'unknown')),
            };
        } catch (\Exception $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    /**
     * IPN webhooks are also signed with kr-hash, but using the API password
     * (not the HMAC key which is used for browser returns).
     */
    public function verifyWebhook(Request $request): bool
    {
        $krAnswer = $request->input('kr-answer');
        $krHash = $request->input('kr-hash');
        $password = (string) payment_gateway_setting('izipay_api_password', '');

        if (empty($krAnswer) || empty($krHash) || empty($password)) {
            return false;
        }

        $expected = hash_hmac('sha256', $krAnswer, $password);

        return hash_equals($expected, $krHash);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $answer = json_decode($request->input('kr-answer', '{}'), true) ?: [];
        $transaction = $answer['transactions'][0] ?? [];
        $orderStatus = $answer['orderStatus'] ?? 'unknown';

        $status = match ($orderStatus) {
            'PAID' => 'completed',
            'RUNNING', 'PARTIALLY_PAID' => 'pending',
            'UNPAID', 'ABANDONED', 'EXPIRED' => 'failed',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $answer['orderDetails']['orderId'] ?? ($transaction['uuid'] ?? null),
            status: $status,
            eventType: $orderStatus,
            metadata: $answer,
        );
    }

    /**
     * Browser returns are signed with the HMAC-SHA256 key.
     */
    protected function isValidSignature(string $krAnswer, string $krHash): bool
    {
        $hmacKey = (string) payment_gateway_setting('izipay_hmac_key', '');

        if (empty($hmacKey)) {
            return false;
        }

        $expected = hash_hmac('sha256', $krAnswer, $hmacKey);

        return hash_equals($expected, $krHash);
    }

    public function getClientConfig(): array
    {
        return [
            'public_key' => payment_gateway_setting('izipay_public_key', ''),
            'sandbox' => (bool) payment_gateway_setting('izipay_sandbox', true),
        ];
    }
}
