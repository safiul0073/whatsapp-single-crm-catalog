<?php

namespace App\Modules\PaymentGateways\Services;

use App\Modules\ManualPaymentMethods\Models\ManualPaymentMethod;
use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\Drivers\BitPayPaymentGateway;
use App\Modules\PaymentGateways\Drivers\CoinbaseCommercePaymentGateway;
use App\Modules\PaymentGateways\Drivers\FlutterwavePaymentGateway;
use App\Modules\PaymentGateways\Drivers\IzipayPaymentGateway;
use App\Modules\PaymentGateways\Drivers\LogPaymentGateway;
use App\Modules\PaymentGateways\Drivers\ManualPaymentGateway;
use App\Modules\PaymentGateways\Drivers\MercadoPagoPaymentGateway;
use App\Modules\PaymentGateways\Drivers\MolliePaymentGateway;
use App\Modules\PaymentGateways\Drivers\NowPaymentsPaymentGateway;
use App\Modules\PaymentGateways\Drivers\PayPalPaymentGateway;
use App\Modules\PaymentGateways\Drivers\PaystackPaymentGateway;
use App\Modules\PaymentGateways\Drivers\RazorpayPaymentGateway;
use App\Modules\PaymentGateways\Drivers\SslCommerzPaymentGateway;
use App\Modules\PaymentGateways\Drivers\StripePaymentGateway;
use App\Modules\PaymentGateways\Drivers\XenditPaymentGateway;

class PaymentGatewayManager
{
    /**
     * Resolve the payment gateway driver by name.
     * If no name given, uses the active gateway from settings.
     */
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        if ($name === null) {
            $enabled = $this->getEnabledGatewayNames();
            $name = $enabled[0] ?? 'log';
        }

        return match ($name) {
            'stripe' => app(StripePaymentGateway::class),
            'paypal' => app(PayPalPaymentGateway::class),
            'razorpay' => app(RazorpayPaymentGateway::class),
            'sslcommerz' => app(SslCommerzPaymentGateway::class),
            'paystack' => app(PaystackPaymentGateway::class),
            'flutterwave' => app(FlutterwavePaymentGateway::class),
            'mercadopago' => app(MercadoPagoPaymentGateway::class),
            'izipay' => app(IzipayPaymentGateway::class),
            'mollie' => app(MolliePaymentGateway::class),
            'xendit' => app(XenditPaymentGateway::class),
            'nowpayments' => app(NowPaymentsPaymentGateway::class),
            'coinbasecommerce' => app(CoinbaseCommercePaymentGateway::class),
            'bitpay' => app(BitPayPaymentGateway::class),
            default => $this->resolveManualOrFallback($name),
        };
    }

    /**
     * Alias for driver().
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        return $this->driver($name);
    }

    /**
     * Get all enabled gateway names (automated + manual).
     *
     * @return array<string>
     */
    public function getEnabledGatewayNames(): array
    {
        $automated = [
            'stripe',
            'paypal',
            'razorpay',
            'sslcommerz',
            'paystack',
            'flutterwave',
            'mercadopago',
            'izipay',
            'mollie',
            'xendit',
            'nowpayments',
            'coinbasecommerce',
            'bitpay',
        ];
        $manual = ManualPaymentMethod::pluck('slug')->toArray();

        return collect(array_merge($automated, $manual))
            ->filter(fn (string $name) => (bool) payment_gateway_setting("{$name}_enabled", false))
            ->values()
            ->toArray();
    }

    /**
     * Resolve a manual payment gateway driver or fall back to the log driver.
     */
    protected function resolveManualOrFallback(string $name): PaymentGatewayInterface
    {
        if (ManualPaymentMethod::where('slug', $name)->exists()) {
            return new ManualPaymentGateway($name);
        }

        return app(LogPaymentGateway::class);
    }

    /**
     * Get all enabled gateway driver instances.
     *
     * @return array<string, PaymentGatewayInterface>
     */
    public function getEnabledGateways(): array
    {
        $gateways = [];

        foreach ($this->getEnabledGatewayNames() as $name) {
            $gateways[$name] = $this->driver($name);
        }

        return $gateways;
    }

    /**
     * Check if a specific gateway is enabled.
     */
    public function isEnabled(string $name): bool
    {
        return (bool) payment_gateway_setting("{$name}_enabled", false);
    }
}
