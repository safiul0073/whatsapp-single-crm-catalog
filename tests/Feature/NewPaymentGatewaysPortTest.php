<?php

use App\Modules\PaymentGateways\Drivers\BitPayPaymentGateway;
use App\Modules\PaymentGateways\Drivers\CoinbaseCommercePaymentGateway;
use App\Modules\PaymentGateways\Drivers\IzipayPaymentGateway;
use App\Modules\PaymentGateways\Drivers\MercadoPagoPaymentGateway;
use App\Modules\PaymentGateways\Drivers\MolliePaymentGateway;
use App\Modules\PaymentGateways\Drivers\NowPaymentsPaymentGateway;
use App\Modules\PaymentGateways\Drivers\XenditPaymentGateway;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use App\Modules\PaymentGatewaySettings\Database\Seeders\PaymentGatewaySettingsSeeder;
use App\Modules\PaymentGatewaySettings\Services\PaymentGatewaySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('registers the Softivus payment gateway settings tabs', function (): void {
    expect(config('payment-gateway-settings'))->toHaveKeys([
        'mercadopago',
        'izipay',
        'mollie',
        'xendit',
        'nowpayments',
        'coinbasecommerce',
        'bitpay',
    ]);

    expect(config('payment-gateway-settings.mercadopago.settings'))->toHaveKeys([
        'mercadopago_enabled',
        'mercadopago_access_token',
        'mercadopago_webhook_secret',
    ])->and(config('payment-gateway-settings.izipay.settings'))->toHaveKeys([
        'izipay_enabled',
        'izipay_shop_id',
        'izipay_api_password',
        'izipay_hmac_key',
    ])->and(config('payment-gateway-settings.mollie.settings'))->toHaveKey('mollie_api_key')
        ->and(config('payment-gateway-settings.xendit.settings'))->toHaveKey('xendit_webhook_token')
        ->and(config('payment-gateway-settings.nowpayments.settings'))->toHaveKey('nowpayments_ipn_secret')
        ->and(config('payment-gateway-settings.coinbasecommerce.settings'))->toHaveKey('coinbasecommerce_webhook_secret')
        ->and(config('payment-gateway-settings.bitpay.settings'))->toHaveKey('bitpay_api_token');
});

it('seeds default settings for the new Softivus payment gateways', function (): void {
    $this->seed(PaymentGatewaySettingsSeeder::class);

    $settings = app(PaymentGatewaySettingsService::class);

    expect($settings->get('mercadopago_enabled'))->toBeFalse()
        ->and($settings->get('izipay_sandbox'))->toBeTrue()
        ->and($settings->get('mollie_api_key'))->toBe('')
        ->and($settings->get('xendit_secret_key'))->toBe('')
        ->and($settings->get('nowpayments_api_key'))->toBe('')
        ->and($settings->get('coinbasecommerce_api_key'))->toBe('')
        ->and($settings->get('bitpay_sandbox'))->toBeTrue();
});

it('resolves the new Softivus payment gateway drivers', function (string $gateway, string $driver): void {
    expect(app(PaymentGatewayManager::class)->driver($gateway))->toBeInstanceOf($driver);
})->with([
    'mercado pago' => ['mercadopago', MercadoPagoPaymentGateway::class],
    'izipay' => ['izipay', IzipayPaymentGateway::class],
    'mollie' => ['mollie', MolliePaymentGateway::class],
    'xendit' => ['xendit', XenditPaymentGateway::class],
    'nowpayments' => ['nowpayments', NowPaymentsPaymentGateway::class],
    'coinbase commerce' => ['coinbasecommerce', CoinbaseCommercePaymentGateway::class],
    'bitpay' => ['bitpay', BitPayPaymentGateway::class],
]);

it('includes enabled Softivus payment gateways in checkout selection', function (): void {
    $settings = app(PaymentGatewaySettingsService::class);
    $settings->set('mercadopago_enabled', true);
    $settings->set('mollie_enabled', true);
    $settings->set('bitpay_enabled', false);

    expect(app(PaymentGatewayManager::class)->getEnabledGatewayNames())
        ->toContain('mercadopago', 'mollie')
        ->not->toContain('bitpay');
});

it('validates signed webhook requests for new gateways', function (): void {
    $settings = app(PaymentGatewaySettingsService::class);
    $settings->set('coinbasecommerce_webhook_secret', 'coinbase-secret');
    $settings->set('nowpayments_ipn_secret', 'now-secret');
    $settings->set('xendit_webhook_token', 'xendit-token');

    $coinbasePayload = json_encode(['event' => ['type' => 'charge:confirmed']], JSON_THROW_ON_ERROR);
    $coinbaseRequest = Request::create('/webhooks/payments/coinbasecommerce', 'POST', [], [], [], [
        'HTTP_X_CC_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $coinbasePayload, 'coinbase-secret'),
    ], $coinbasePayload);

    $nowPayload = ['payment_status' => 'finished', 'payment_id' => 123, 'order_id' => 'order-1'];
    $sortedNowPayload = $nowPayload;
    ksort($sortedNowPayload);
    $nowRequest = Request::create('/webhooks/payments/nowpayments', 'POST', $nowPayload, [], [], [
        'HTTP_X_NOWPAYMENTS_SIG' => hash_hmac('sha512', json_encode($sortedNowPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'now-secret'),
    ]);

    $xenditRequest = Request::create('/webhooks/payments/xendit', 'POST', [], [], [], [
        'HTTP_X_CALLBACK_TOKEN' => 'xendit-token',
    ]);

    expect(app(CoinbaseCommercePaymentGateway::class)->verifyWebhook($coinbaseRequest))->toBeTrue()
        ->and(app(NowPaymentsPaymentGateway::class)->verifyWebhook($nowRequest))->toBeTrue()
        ->and(app(XenditPaymentGateway::class)->verifyWebhook($xenditRequest))->toBeTrue();
});
