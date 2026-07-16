<?php

namespace App\Modules\PaymentGateways\Contracts;

use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Gateway identifier (e.g. 'stripe', 'paypal', 'log').
     */
    public function name(): string;

    /**
     * Create a new payment / order / intent on the gateway.
     *
     * Returns a polymorphic PaymentResponse:
     * - Redirect gateways (PayPal, SSLCommerz, Paystack): returns redirect URL
     * - Embedded gateways (Stripe, Razorpay, Flutterwave): returns client data (token/secret)
     * - Log driver: returns immediate completion
     */
    public function createPayment(PaymentData $data): PaymentResponse;

    /**
     * Verify a payment after the user returns from redirect or JS callback.
     *
     * Each gateway verifies differently:
     * - Stripe: retrieves PaymentIntent status
     * - PayPal: captures the approved order
     * - Razorpay: verifies SHA256 signature
     * - SSLCommerz: calls validation API
     * - Paystack: calls verify transaction API
     * - Flutterwave: calls verify transaction API
     */
    public function verifyPayment(Request $request): PaymentResponse;

    /**
     * Verify the authenticity of an incoming webhook request.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Process a verified webhook and return the result.
     */
    public function handleWebhook(Request $request): WebhookResult;

    /**
     * Get public configuration for the frontend (public keys, SDK URLs, sandbox flag).
     *
     * This data is safe to expose to the client:
     * - Stripe: ['publishable_key' => 'pk_...']
     * - Razorpay: ['key_id' => 'rzp_...']
     * - Paystack: ['public_key' => 'pk_...']
     */
    public function getClientConfig(): array;
}
