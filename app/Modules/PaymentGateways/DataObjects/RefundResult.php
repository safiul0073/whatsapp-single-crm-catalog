<?php

namespace App\Modules\PaymentGateways\DataObjects;

final readonly class RefundResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $gatewayRefundId = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}

    public static function success(string $gatewayRefundId, string $status = 'completed', array $metadata = []): self
    {
        return new self(
            success: true,
            status: $status,
            gatewayRefundId: $gatewayRefundId,
            metadata: $metadata,
        );
    }

    public static function pending(string $gatewayRefundId, array $metadata = []): self
    {
        return new self(
            success: true,
            status: 'pending',
            gatewayRefundId: $gatewayRefundId,
            metadata: $metadata,
        );
    }

    public static function failed(string $message, array $metadata = []): self
    {
        return new self(
            success: false,
            status: 'failed',
            message: $message,
            metadata: $metadata,
        );
    }
}
