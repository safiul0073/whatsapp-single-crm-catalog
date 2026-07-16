<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\Shared\Services\DashboardService;
use App\Modules\Shared\Widgets\BaseWidget;

class RecentChannelsWidget extends BaseWidget
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function id(): string
    {
        return 'admin-recent-channels';
    }

    public function title(): string
    {
        return __('Recent Channels');
    }

    public function render(): string
    {
        $recentChannels = $this->dashboardService->getRecentChannels();

        return $this->view('widgets.admin.recent-channels', compact('recentChannels'));
    }

    public function position(): int
    {
        return 31;
    }

    public function width(): string
    {
        return 'half';
    }
}
