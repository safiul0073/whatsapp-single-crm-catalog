<?php

namespace App\Modules\PaymentGateways\DataObjects;

final readonly class WebhookResult
{
    public function __construct(
        public ?string $gatewayPaymentId = null,
        public ?string $status = null,
        public ?string $eventType = null,
        public array $metadata = [],
    ) {}
}
