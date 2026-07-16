<?php

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;

if (! function_exists('payment_gateway')) {
    /**
     * Get the active payment gateway driver instance.
     *
     * Usage:
     *   payment_gateway()->name()                    // 'stripe'
     *   payment_gateway()->getClientConfig()          // ['publishable_key' => 'pk_...']
     *   payment_gateway('paypal')->createPayment($data)  // Use a specific gateway
     */
    function payment_gateway(?string $driver = null): PaymentGatewayInterface
    {
        return app(PaymentGatewayManager::class)->driver($driver);
    }
}

if (! function_exists('enabled_payment_gateways')) {
    /**
     * Get all enabled payment gateway names.
     *
     * Usage:
     *   enabled_payment_gateways()  // ['stripe', 'paypal']
     */
    function enabled_payment_gateways(): array
    {
        return app(PaymentGatewayManager::class)->getEnabledGatewayNames();
    }
}
