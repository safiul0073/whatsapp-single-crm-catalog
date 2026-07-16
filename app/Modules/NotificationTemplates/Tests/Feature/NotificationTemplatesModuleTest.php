<?php

namespace App\Modules\NotificationTemplates\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NotificationTemplatesModuleTest extends TestCase
{
    public function test_notification_templates_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('notification-templates');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.notification-templates.index'));
        $this->assertTrue(Route::has('admin.notification-send.create'));
        $this->assertTrue(Route::has('admin.notification-logs.index'));
    }
}
