<?php

namespace App\Modules\PaymentGatewaySettings\Providers;

use App\Modules\PaymentGatewaySettings\Services\PaymentGatewaySettingsService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class PaymentGatewaySettingsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewaySettingsService::class);
    }
}
