<?php

namespace App\Modules\PaymentGateways\Providers;

use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use App\Modules\PaymentGateways\Services\PaymentService;
use App\Modules\PaymentGateways\Services\WebhookLogService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class PaymentGatewaysServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService($app->make(PaymentGatewayManager::class));
        });
        $this->app->singleton(WebhookLogService::class);
    }
}
