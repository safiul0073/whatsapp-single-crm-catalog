<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\BaseWidget;

class StatsWidget extends BaseWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-stats';
    }

    public function title(): string
    {
        return __('Overview');
    }

    public function render(): string
    {
        $stats = $this->dashboardService->getAdminOverviewStats();

        return $this->view('widgets.admin.stats', compact('stats'));
    }

    public function position(): int
    {
        return 10;
    }

    public function width(): string
    {
        return 'full';
    }
}
