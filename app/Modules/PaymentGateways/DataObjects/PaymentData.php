<?php

namespace App\Modules\PaymentGateways\DataObjects;

final readonly class PaymentData
{
    public function __construct(
        public float $amount,
        public string $currency = 'USD',
        public ?string $description = null,
        public ?string $paymentMethod = null,
        public ?string $userId = null,
        public ?string $userType = null,
        public array $metadata = [],
        public ?string $returnUrl = null,
        public ?string $cancelUrl = null,
    ) {}

    /**
     * Quick factory: PaymentData::make(29.99, 'USD')
     */
    public static function make(float $amount, string $currency = 'USD'): self
    {
        return new self(amount: $amount, currency: $currency);
    }
}
