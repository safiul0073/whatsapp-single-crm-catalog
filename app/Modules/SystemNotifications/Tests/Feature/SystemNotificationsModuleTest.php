<?php

namespace App\Modules\SystemNotifications\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SystemNotificationsModuleTest extends TestCase
{
    public function test_system_notifications_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('system-notifications');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.system-notifications.index'));
        $this->assertTrue(Route::has('admin.system-notifications.send'));
    }
}
