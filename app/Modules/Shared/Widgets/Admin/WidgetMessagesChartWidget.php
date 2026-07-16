<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\BaseWidget;

class WidgetMessagesChartWidget extends BaseWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-widget-messages';
    }

    public function title(): string
    {
        return __('Widget Messages');
    }

    public function render(): string
    {
        return $this->view('widgets.admin.filterable-chart', [
            'widgetId' => $this->id(),
            'title' => $this->title(),
            'chartType' => 'area',
            'chartHeight' => 300,
            'chartColors' => ['#6366f1'],
            'periods' => [
                'daily' => [
                    'label' => __('Daily'),
                    'data' => $this->dashboardService->getDailyWidgetMessagesChartData(),
                ],
                'monthly' => [
                    'label' => __('Monthly'),
                    'data' => $this->dashboardService->getMonthlyWidgetMessagesChartData(),
                ],
            ],
        ]);
    }

    public function position(): int
    {
        return 18;
    }

    public function width(): string
    {
        return 'half';
    }
}
