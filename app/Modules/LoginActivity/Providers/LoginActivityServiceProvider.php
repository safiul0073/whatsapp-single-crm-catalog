<?php

namespace App\Modules\LoginActivity\Providers;

use App\Modules\LoginActivity\Services\LoginActivityService;
use App\Modules\LoginActivity\Widgets\SalesOverviewWidget;
use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Services\WidgetRegistry;

class LoginActivityServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginActivityService::class);
    }

    protected function bootModule(array $module): void
    {
        if ($this->app->bound(WidgetRegistry::class)) {
            $dashboardService = $this->app->make(DashboardService::class);

            $this->app->make(WidgetRegistry::class)->register(new SalesOverviewWidget($dashboardService));
        }
    }
}
