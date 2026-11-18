<?php

namespace App\Modules\Shipping\Services\Carriers;

use RuntimeException;

/**
 * A courier API call that failed in a way staff can act on.
 */
class CarrierApiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $carrierCode = null, public readonly ?int $httpStatus = null)
    {
        parent::__construct($message);
    }

    public function summary(): string
    {
        return trim(($this->carrierCode ? '['.$this->carrierCode.'] ' : '').$this->getMessage());
    }
}
