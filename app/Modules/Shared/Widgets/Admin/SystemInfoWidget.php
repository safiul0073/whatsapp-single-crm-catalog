<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\BaseWidget;

class SystemInfoWidget extends BaseWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-system-info';
    }

    public function title(): string
    {
        return __('System Information');
    }

    public function render(): string
    {
        $systemInfo = $this->dashboardService->getSystemInfo();

        return $this->view('widgets.admin.system-info', compact('systemInfo'));
    }

    public function position(): int
    {
        return 60;
    }

    public function width(): string
    {
        return 'half';
    }
}
