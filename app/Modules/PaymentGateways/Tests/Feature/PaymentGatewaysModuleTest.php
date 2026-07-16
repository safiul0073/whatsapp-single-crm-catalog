<?php

namespace App\Modules\PaymentGateways\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentGatewaysModuleTest extends TestCase
{
    public function test_payment_gateways_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('payment-gateways');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.payments.index'));
        $this->assertFalse(Route::has('admin.payments.refund'));
        $this->assertFalse(Route::has('admin.refunds.index'));
        $this->assertTrue(Route::has('admin.webhook-logs.index'));
        $this->assertTrue(Route::has('webhooks.payments'));
    }
}
