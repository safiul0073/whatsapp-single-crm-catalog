<?php

namespace App\Modules\AuditLog\Tests\Feature;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditLogModuleTest extends TestCase
{
    public function test_audit_log_module_is_registered(): void
    {
        $module = app(ModuleRegistry::class)->find('audit-log');

        $this->assertNotNull($module);
        $this->assertNotNull($module['descriptor']);
        $this->assertTrue(Route::has('admin.audit-logs.index'));
    }
}
