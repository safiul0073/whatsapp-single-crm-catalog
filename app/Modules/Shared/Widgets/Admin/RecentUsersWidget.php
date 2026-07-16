<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\BaseWidget;

class RecentUsersWidget extends BaseWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-recent-users';
    }

    public function title(): string
    {
        return __('Recent Users');
    }

    public function render(): string
    {
        $recentUsers = $this->dashboardService->getRecentUsers();

        return $this->view('widgets.admin.recent-users', compact('recentUsers'));
    }

    public function position(): int
    {
        return 40;
    }

    public function width(): string
    {
        return 'half';
    }
}
