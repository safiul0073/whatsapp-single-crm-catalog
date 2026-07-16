<?php

use App\Modules\PaymentGatewaySettings\Services\PaymentGatewaySettingsService;

if (! function_exists('payment_gateway_setting')) {
    /**
     * Get a payment gateway setting value.
     *
     * Usage:
     *   payment_gateway_setting('payment_gateway')              // 'stripe'
     *   payment_gateway_setting('stripe_secret_key')             // 'sk_...'
     *   payment_gateway_setting('payment_currency', 'USD')       // With fallback
     */
    function payment_gateway_setting(string $key, mixed $default = null): mixed
    {
        return app(PaymentGatewaySettingsService::class)->get($key, $default);
    }
}
