<?php

namespace App\Modules\LoginActivity\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginActivityModuleTest extends TestCase
{
    public function test_login_activity_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('login-activity');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.login-activity.index'));
    }
}
