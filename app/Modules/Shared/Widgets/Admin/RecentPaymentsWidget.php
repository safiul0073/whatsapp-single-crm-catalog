<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\BaseWidget;

class RecentPaymentsWidget extends BaseWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-recent-payments';
    }

    public function title(): string
    {
        return __('Recent Payments');
    }

    public function render(): string
    {
        $recentPayments = $this->dashboardService->getRecentPayments();

        return $this->view('widgets.admin.recent-payments', compact('recentPayments'));
    }

    public function position(): int
    {
        return 30;
    }

    public function width(): string
    {
        return 'half';
    }
}
