<?php

namespace App\Modules\SystemNotifications\Providers;

use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\SystemNotifications\Services\SystemNotificationService;

class SystemNotificationsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemNotificationService::class);
    }
}
