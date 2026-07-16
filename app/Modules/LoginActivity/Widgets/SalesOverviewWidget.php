<?php

namespace App\Modules\LoginActivity\Widgets;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\ChartWidget;

class SalesOverviewWidget extends ChartWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-platform-activity';
    }

    public function title(): string
    {
        return __('Platform Activity');
    }

    public function chartType(): string
    {
        return 'area';
    }

    protected function getData(): array
    {
        return $this->dashboardService->getPlatformActivityChartData();
    }

    public function position(): int
    {
        return 15;
    }

    public function width(): string
    {
        return 'half';
    }

    public function panel(): string
    {
        return 'admin';
    }
}
