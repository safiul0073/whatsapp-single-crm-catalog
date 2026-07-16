<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ManualPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @param  string  $slug  The manual payment method slug (e.g. 'bank-transfer', 'bkash')
     */
    public function __construct(
        protected string $slug
    ) {}

    public function name(): string
    {
        return $this->slug;
    }

    /**
     * Create a pending payment that awaits admin approval.
     *
     * The response metadata includes instructions and user-submitted field data
     * so the frontend can display next steps to the customer.
     */
    public function createPayment(PaymentData $data): PaymentResponse
    {
        $id = 'manual_'.$this->slug.'_'.Str::uuid();

        $userFields = json_decode(
            payment_gateway_setting("{$this->slug}_user_fields", '[]'),
            true
        ) ?: [];

        return PaymentResponse::pending($id, [
            'driver' => 'manual',
            'method' => $this->slug,
            'instructions' => payment_gateway_setting("{$this->slug}_instructions", ''),
            'user_fields_definition' => $userFields,
            'user_submitted_data' => $data->metadata['user_fields'] ?? [],
        ]);
    }

    /**
     * Manual payments are verified via admin approval, not gateway callback.
     */
    public function verifyPayment(Request $request): PaymentResponse
    {
        $id = $request->get('gateway_payment_id', '');

        return PaymentResponse::pending($id, [
            'note' => 'Manual payments are verified via admin approval.',
        ]);
    }

    /**
     * Manual gateways do not receive webhooks.
     */
    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        return new WebhookResult(
            status: 'ignored',
            eventType: 'manual.noop',
        );
    }

    /**
     * Return public configuration for the frontend.
     *
     * Includes payment instructions and user field definitions
     * so the checkout form can render the correct inputs.
     */
    public function getClientConfig(): array
    {
        $userFields = json_decode(
            payment_gateway_setting("{$this->slug}_user_fields", '[]'),
            true
        ) ?: [];

        return [
            'gateway' => $this->slug,
            'type' => 'manual',
            'instructions' => payment_gateway_setting("{$this->slug}_instructions", ''),
            'user_fields' => $userFields,
        ];
    }
}
