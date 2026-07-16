<?php

namespace App\Modules\ManualPaymentMethods\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ManualPaymentMethodsModuleTest extends TestCase
{
    public function test_manual_payment_methods_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('manual-payment-methods');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.manual-payment-methods.store'));
    }
}
