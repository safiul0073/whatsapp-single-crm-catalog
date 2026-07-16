<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwavePaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.flutterwave.com';

    public function name(): string
    {
        return 'flutterwave';
    }

    /**
     * Ensure required Flutterwave credentials are configured.
     *
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        $secretKey = payment_gateway_setting('flutterwave_secret_key', '');
        $publicKey = payment_gateway_setting('flutterwave_public_key', '');

        if (empty($secretKey) || empty($publicKey)) {
            throw new RuntimeException(
                'Flutterwave API keys are not configured. Set them in Settings → Payment Gateways.'
            );
        }
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $secretKey = payment_gateway_setting('flutterwave_secret_key', '');
            $txRef = $data->metadata['tx_ref'] ?? 'flw_'.uniqid();

            $response = Http::withToken($secretKey)
                ->post("{$this->baseUrl}/v3/payments", array_filter([
                    'tx_ref' => $txRef,
                    'amount' => $data->amount,
                    'currency' => strtoupper($data->currency),
                    'redirect_url' => $data->returnUrl,
                    'customer' => array_filter([
                        'email' => $data->metadata['email'] ?? null,
                        'name' => $data->metadata['customer_name'] ?? null,
                        'phonenumber' => $data->metadata['customer_phone'] ?? null,
                    ]),
                    'customizations' => array_filter([
                        'title' => config('app.name'),
                        'description' => $data->description,
                    ]),
                    'meta' => array_filter([
                        'user_id' => $data->userId,
                        'user_type' => $data->userType,
                    ]),
                ]));

            $result = $response->json();

            if (($result['status'] ?? '') !== 'success') {
                return PaymentResponse::failed($result['message'] ?? 'Failed to create Flutterwave payment.');
            }

            $link = $result['data']['link'] ?? null;

            if (! $link) {
                return PaymentResponse::failed('Flutterwave payment link not found in response.');
            }

            return PaymentResponse::redirect($txRef, $link, [
                'tx_ref' => $txRef,
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $secretKey = payment_gateway_setting('flutterwave_secret_key', '');
            $transactionId = $request->get('transaction_id');
            $txRef = $request->get('tx_ref');

            if (empty($transactionId)) {
                return PaymentResponse::failed('Missing transaction_id from Flutterwave redirect.');
            }

            $response = Http::withToken($secretKey)
                ->get("{$this->baseUrl}/v3/transactions/{$transactionId}/verify");

            $result = $response->json();

            if (($result['status'] ?? '') !== 'success') {
                return PaymentResponse::failed($result['message'] ?? 'Flutterwave verification failed.');
            }

            $data = $result['data'] ?? [];
            $gatewayStatus = $data['status'] ?? '';

            if ($gatewayStatus === 'successful') {
                return PaymentResponse::completed($txRef ?? (string) $transactionId, [
                    'flw_transaction_id' => $transactionId,
                    'amount' => $data['amount'] ?? null,
                    'currency' => $data['currency'] ?? null,
                    'payment_type' => $data['payment_type'] ?? null,
                    'charged_amount' => $data['charged_amount'] ?? null,
                ]);
            }

            return PaymentResponse::failed("Flutterwave transaction status: {$gatewayStatus}");
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $secretHash = payment_gateway_setting('flutterwave_secret_hash', '');

        if (empty($secretHash)) {
            return false;
        }

        $verifHash = $request->header('verif-hash', '');

        return hash_equals($secretHash, $verifHash);
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();
        $eventType = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        $gatewayPaymentId = $data['tx_ref'] ?? ($data['id'] ?? null);

        $status = match ($eventType) {
            'charge.completed' => ($data['status'] ?? '') === 'successful' ? 'completed' : 'failed',
            'transfer.completed' => 'completed',
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
            'public_key' => payment_gateway_setting('flutterwave_public_key', ''),
        ];
    }
}
