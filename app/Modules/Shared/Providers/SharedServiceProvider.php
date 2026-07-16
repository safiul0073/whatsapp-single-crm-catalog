<?php

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\Shared\Widgets\Admin\ChannelUsageChartWidget;
use App\Modules\Shared\Widgets\Admin\MessagesByChannelChartWidget;
use App\Modules\Shared\Widgets\Admin\RecentChannelsWidget;
use App\Modules\Shared\Widgets\Admin\StatsWidget;
use App\Modules\Shared\Widgets\Admin\WidgetMessagesChartWidget;
use App\Modules\Shared\Widgets\User\QuickLinksWidget;
use App\Modules\Shared\Widgets\User\WelcomeWidget;
use App\Services\WidgetRegistry;

class SharedServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        //
    }

    protected function bootModule(array $module): void
    {
        $registry = $this->app->make(WidgetRegistry::class);
        $dashboardService = $this->app->make(DashboardService::class);

        $registry->register(new StatsWidget($dashboardService));
        $registry->register(new ChannelUsageChartWidget($dashboardService));
        $registry->register(new MessagesByChannelChartWidget($dashboardService));
        $registry->register(new WidgetMessagesChartWidget($dashboardService));
        $registry->register(new RecentChannelsWidget($dashboardService));

        $registry->register(new WelcomeWidget);
        $registry->register(new QuickLinksWidget);
    }
}
