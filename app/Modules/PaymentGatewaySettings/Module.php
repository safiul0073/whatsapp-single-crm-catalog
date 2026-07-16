<?php

namespace App\Modules\PaymentGatewaySettings;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'payment-gateway-settings';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'payment-gateway-settings.view' => 'View payment gateway settings',
                'payment-gateway-settings.edit' => 'Edit payment gateway settings',
            ],
        ];
    }
}
