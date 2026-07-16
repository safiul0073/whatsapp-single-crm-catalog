<?php

namespace App\Modules\ManualPaymentMethods;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'manual-payment-methods';
    }
}
