<?php

namespace App\Modules\PaymentGateways\DataObjects;

final readonly class PaymentResponse
{
    /**
     * @param  string  $status  pending, processing, completed, failed
     * @param  ?string  $gatewayPaymentId  Gateway's reference ID
     * @param  ?string  $redirectUrl  For redirect gateways (PayPal, SSLCommerz, Paystack)
     * @param  ?array  $clientData  For embedded gateways (Stripe client_secret, Razorpay order_id)
     * @param  ?string  $message  Error message or success note
     * @param  array  $metadata  Additional gateway-specific data
     */
    public function __construct(
        public string $status,
        public ?string $gatewayPaymentId = null,
        public ?string $redirectUrl = null,
        public ?array $clientData = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}

    /**
     * Is this a redirect-based flow? (PayPal, SSLCommerz, Paystack)
     */
    public function isRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }

    /**
     * Does this require client-side action? (Stripe, Razorpay, Flutterwave)
     */
    public function requiresClientAction(): bool
    {
        return $this->clientData !== null;
    }

    /**
     * Is the payment already complete? (Log driver, or immediate capture)
     */
    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * For redirect gateways: user leaves site → pays → returns.
     */
    public static function redirect(string $gatewayPaymentId, string $url, array $metadata = []): self
    {
        return new self(
            status: 'pending',
            gatewayPaymentId: $gatewayPaymentId,
            redirectUrl: $url,
            metadata: $metadata,
        );
    }

    /**
     * For embedded gateways: return token/secret to frontend JS.
     */
    public static function clientAction(string $gatewayPaymentId, array $clientData, array $metadata = []): self
    {
        return new self(
            status: 'pending',
            gatewayPaymentId: $gatewayPaymentId,
            clientData: $clientData,
            metadata: $metadata,
        );
    }

    /**
     * Immediate completion (Log driver, or direct charge that succeeded).
     */
    public static function completed(string $gatewayPaymentId, array $metadata = []): self
    {
        return new self(
            status: 'completed',
            gatewayPaymentId: $gatewayPaymentId,
            metadata: $metadata,
        );
    }

    /**
     * For manual/offline gateways: payment created, awaiting admin approval.
     * Unlike redirect() or clientAction(), no redirect URL or client data is set.
     */
    public static function pending(string $gatewayPaymentId, array $metadata = []): self
    {
        return new self(
            status: 'pending',
            gatewayPaymentId: $gatewayPaymentId,
            metadata: $metadata,
        );
    }

    public static function failed(string $message, array $metadata = []): self
    {
        return new self(
            status: 'failed',
            message: $message,
            metadata: $metadata,
        );
    }
}
