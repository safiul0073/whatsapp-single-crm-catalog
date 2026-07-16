<?php

namespace App\Modules\AuditLog\Providers;

use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class AuditLogServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogService::class);
    }
}
