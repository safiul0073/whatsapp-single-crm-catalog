<?php

namespace App\Modules\PaymentGateways\Policies;

use App\Modules\PaymentGateways\Models\Payment;
use Illuminate\Contracts\Auth\Authenticatable;

class PaymentPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(Authenticatable $user, Payment $payment): bool
    {
        return $user->can('payments.view');
    }
}
