<?php

namespace App\Modules\Languages\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LanguagesModuleTest extends TestCase
{
    public function test_languages_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('languages');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.languages.index'));
        $this->assertTrue(Route::has('admin.languages.translations'));
    }
}
