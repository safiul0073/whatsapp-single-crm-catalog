<?php

namespace App\Modules\Media\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MediaModuleTest extends TestCase
{
    public function test_media_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('media');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.media.index'));
    }
}
