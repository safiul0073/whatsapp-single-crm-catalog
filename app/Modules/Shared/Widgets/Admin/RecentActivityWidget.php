<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-recent-activity';
    }

    public function title(): string
    {
        return __('Recent Activity');
    }

    public function render(): string
    {
        $recentActivity = $this->dashboardService->getUnifiedRecentActivity();

        return $this->view('widgets.admin.recent-activity', compact('recentActivity'));
    }

    public function position(): int
    {
        return 20;
    }

    public function width(): string
    {
        return 'half';
    }
}
