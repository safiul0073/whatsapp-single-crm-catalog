<?php

namespace App\Modules\PaymentGatewaySettings\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentGatewaySettingsModuleTest extends TestCase
{
    public function test_payment_gateway_settings_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('payment-gateway-settings');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.payment-gateway-settings.index'));
    }
}
