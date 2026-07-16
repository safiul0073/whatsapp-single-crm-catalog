<?php

namespace App\Modules\Settings\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SettingsModuleTest extends TestCase
{
    public function test_settings_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('settings');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.settings.index'));
    }
}
