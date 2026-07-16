<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\ChartWidget;

class PlanSubscriptionsChartWidget extends ChartWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-plan-subscriptions-chart';
    }

    public function title(): string
    {
        return __('Plan Subscriptions');
    }

    public function chartType(): string
    {
        return 'donut';
    }

    public function chartHeight(): int
    {
        return 280;
    }

    protected function getData(): array
    {
        return $this->dashboardService->getPlanSubscriptionChartData();
    }

    public function position(): int
    {
        return 25;
    }

    public function width(): string
    {
        return 'half';
    }

    public function cacheFor(): ?int
    {
        return 300;
    }
}
