<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\ChartWidget;

class UserDistributionChartWidget extends ChartWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-user-distribution';
    }

    public function title(): string
    {
        return __('User Distribution by Role');
    }

    public function chartType(): string
    {
        return 'donut';
    }

    public function chartHeight(): int
    {
        return 260;
    }

    protected function getData(): array
    {
        $distribution = $this->dashboardService->getUserRoleDistribution();

        return [
            'series' => array_values($distribution),
            'labels' => array_keys($distribution),
        ];
    }

    public function position(): int
    {
        return 35;
    }

    public function width(): string
    {
        return 'half';
    }

    public function shouldRender(): bool
    {
        return ! empty($this->dashboardService->getUserRoleDistribution());
    }

    public function cacheFor(): ?int
    {
        return 300;
    }
}
