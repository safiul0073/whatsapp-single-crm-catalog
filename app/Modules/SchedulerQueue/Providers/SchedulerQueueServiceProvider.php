<?php

namespace App\Modules\SchedulerQueue\Providers;

use App\Modules\SchedulerQueue\Services\ManagedSchedulerService;
use App\Modules\SchedulerQueue\Services\QueueMonitorService;
use App\Modules\SchedulerQueue\Services\SchedulerRegistry;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class SchedulerQueueServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(SchedulerRegistry::class);
        $this->app->singleton(ManagedSchedulerService::class);
        $this->app->singleton(QueueMonitorService::class);
    }
}
