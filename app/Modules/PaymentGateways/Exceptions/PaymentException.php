<?php

namespace App\Modules\PaymentGateways\Exceptions;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(
        string $message,
        public ?string $gatewayName = null,
        public ?string $gatewayErrorCode = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
