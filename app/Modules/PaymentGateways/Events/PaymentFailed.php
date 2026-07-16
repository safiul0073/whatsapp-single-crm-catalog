<?php

namespace App\Modules\PaymentGateways\Events;

use App\Modules\PaymentGateways\Models\Payment;

class PaymentFailed
{
    public function __construct(
        public Payment $payment
    ) {}
}
