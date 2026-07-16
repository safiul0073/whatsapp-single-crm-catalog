<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-revenue-chart';
    }

    public function title(): string
    {
        return __('Revenue');
    }

    public function chartType(): string
    {
        return 'area';
    }

    public function chartColors(): array
    {
        return ['#22c55e'];
    }

    protected function getData(): array
    {
        return $this->dashboardService->getRevenueChartData();
    }

    public function position(): int
    {
        return 16;
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
