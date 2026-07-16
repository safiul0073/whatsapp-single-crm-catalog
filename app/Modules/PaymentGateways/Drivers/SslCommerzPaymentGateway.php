<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SslCommerzPaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'sslcommerz';
    }

    /**
     * Get the SSLCommerz base URL based on sandbox setting.
     */
    protected function getBaseUrl(): string
    {
        $sandbox = (bool) payment_gateway_setting('sslcommerz_sandbox', true);

        return $sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    /**
     * Ensure required SSLCommerz credentials are configured.
     *
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        $storeId = payment_gateway_setting('sslcommerz_store_id', '');
        $storePassword = payment_gateway_setting('sslcommerz_store_password', '');

        if (empty($storeId) || empty($storePassword)) {
            throw new RuntimeException(
                'SSLCommerz credentials are not configured. Set them in Settings → Payment Gateways.'
            );
        }
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $storeId = payment_gateway_setting('sslcommerz_store_id', '');
            $storePassword = payment_gateway_setting('sslcommerz_store_password', '');
            $baseUrl = $this->getBaseUrl();
            $transactionId = $data->metadata['transaction_id'] ?? uniqid('ssl_');

            $response = Http::asForm()->post("{$baseUrl}/gwprocess/v4/api.php", [
                'store_id' => $storeId,
                'store_passwd' => $storePassword,
                'total_amount' => $data->amount,
                'currency' => strtoupper($data->currency),
                'tran_id' => $transactionId,
                'success_url' => $data->returnUrl,
                'fail_url' => $data->cancelUrl ?? $data->returnUrl,
                'cancel_url' => $data->cancelUrl ?? $data->returnUrl,
                'cus_name' => $data->metadata['customer_name'] ?? 'Customer',
                'cus_email' => $data->metadata['customer_email'] ?? 'customer@example.com',
                'cus_phone' => $data->metadata['customer_phone'] ?? '0000000000',
                'cus_add1' => $data->metadata['customer_address'] ?? 'N/A',
                'cus_city' => $data->metadata['customer_city'] ?? 'N/A',
                'cus_country' => $data->metadata['customer_country'] ?? 'Bangladesh',
                'shipping_method' => 'NO',
                'product_name' => $data->description ?? 'Payment',
                'product_category' => $data->metadata['product_category'] ?? 'General',
                'product_profile' => $data->metadata['product_profile'] ?? 'general',
            ]);

            $result = $response->json();

            if (($result['status'] ?? '') !== 'SUCCESS') {
                return PaymentResponse::failed($result['failedreason'] ?? 'Failed to create SSLCommerz session.');
            }

            return PaymentResponse::redirect($transactionId, $result['GatewayPageURL'], [
                'sessionkey' => $result['sessionkey'] ?? null,
            ]);
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $storeId = payment_gateway_setting('sslcommerz_store_id', '');
            $storePassword = payment_gateway_setting('sslcommerz_store_password', '');
            $baseUrl = $this->getBaseUrl();

            $transactionId = $request->get('tran_id');
            $valId = $request->get('val_id');

            if (empty($valId)) {
                return PaymentResponse::failed('Missing val_id from SSLCommerz redirect.');
            }

            $response = Http::get("{$baseUrl}/validator/api/validationserverAPI.php", [
                'val_id' => $valId,
                'store_id' => $storeId,
                'store_passwd' => $storePassword,
                'format' => 'json',
            ]);

            $result = $response->json();

            if (($result['status'] ?? '') === 'VALID' || ($result['status'] ?? '') === 'VALIDATED') {
                return PaymentResponse::completed($transactionId ?? $valId, [
                    'val_id' => $valId,
                    'amount' => $result['amount'] ?? null,
                    'currency' => $result['currency'] ?? null,
                    'card_type' => $result['card_type'] ?? null,
                    'bank_tran_id' => $result['bank_tran_id'] ?? null,
                ]);
            }

            return PaymentResponse::failed('SSLCommerz validation status: '.($result['status'] ?? 'unknown'));
        } catch (\Exception $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        try {
            $storeId = payment_gateway_setting('sslcommerz_store_id', '');
            $storePassword = payment_gateway_setting('sslcommerz_store_password', '');
            $baseUrl = $this->getBaseUrl();

            $valId = $request->get('val_id');

            if (empty($valId)) {
                return false;
            }

            $response = Http::get("{$baseUrl}/validator/api/validationserverAPI.php", [
                'val_id' => $valId,
                'store_id' => $storeId,
                'store_passwd' => $storePassword,
                'format' => 'json',
            ]);

            $result = $response->json();

            return in_array($result['status'] ?? '', ['VALID', 'VALIDATED']);
        } catch (\Exception) {
            return false;
        }
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $transactionId = $request->get('tran_id');
        $status = $request->get('status');

        $mappedStatus = match ($status) {
            'VALID', 'VALIDATED' => 'completed',
            'FAILED' => 'failed',
            'CANCELLED' => 'canceled',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $transactionId,
            status: $mappedStatus,
            eventType: "ipn.{$status}",
            metadata: $request->all(),
        );
    }

    public function getClientConfig(): array
    {
        return [
            'store_id' => payment_gateway_setting('sslcommerz_store_id', ''),
            'sandbox' => (bool) payment_gateway_setting('sslcommerz_sandbox', true),
        ];
    }
}
