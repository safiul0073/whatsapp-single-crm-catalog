<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\ChartWidget;

class ChannelUsageChartWidget extends ChartWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-channel-usage-chart';
    }

    public function title(): string
    {
        return __('Channel Usage');
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
        return $this->dashboardService->getChannelUsageChartData();
    }

    public function position(): int
    {
        return 26;
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
