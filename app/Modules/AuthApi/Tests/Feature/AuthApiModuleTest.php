<?php

namespace App\Modules\AuthApi\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthApiModuleTest extends TestCase
{
    public function test_it_loads_the_module_descriptor(): void
    {
        $module = app(ModuleRegistry::class)->find('auth-api');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('api.v1.auth.register'));
    }
}
