<?php

namespace App\Modules\AiSettings\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AiSettingsModuleTest extends TestCase
{
    public function test_ai_settings_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('ai-settings');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.ai-settings.index'));
        $this->assertTrue(Route::has('admin.ai-settings.vector-database.index'));
    }
}
