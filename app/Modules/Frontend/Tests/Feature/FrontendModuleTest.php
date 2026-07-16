<?php

namespace App\Modules\Frontend\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FrontendModuleTest extends TestCase
{
    public function test_frontend_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('frontend');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.frontend-themes.index'));
        $this->assertTrue(Route::has('admin.frontend-menus.index'));
        $this->assertTrue(Route::has('admin.frontend-pages.index'));
    }
}
