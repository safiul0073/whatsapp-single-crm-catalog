<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\BaseWidget;

class MessagesByChannelChartWidget extends BaseWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-messages-by-channel';
    }

    public function title(): string
    {
        return __('Messages by Channel');
    }

    public function render(): string
    {
        return $this->view('widgets.admin.filterable-chart', [
            'widgetId' => $this->id(),
            'title' => $this->title(),
            'chartType' => 'bar',
            'chartHeight' => 300,
            'chartColors' => ['#5096f2', '#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4'],
            'periods' => [
                'daily' => [
                    'label' => __('Daily'),
                    'data' => $this->dashboardService->getDailyMessagesByChannelChartData(),
                ],
                'monthly' => [
                    'label' => __('Monthly'),
                    'data' => $this->dashboardService->getMonthlyMessagesByChannelChartData(),
                ],
            ],
        ]);
    }

    public function position(): int
    {
        return 17;
    }

    public function width(): string
    {
        return 'half';
    }
}
