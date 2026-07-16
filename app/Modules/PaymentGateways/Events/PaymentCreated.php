<?php

namespace App\Modules\PaymentGateways\Events;

use App\Modules\PaymentGateways\Models\Payment;

class PaymentCreated
{
    public function __construct(
        public Payment $payment
    ) {}
}
