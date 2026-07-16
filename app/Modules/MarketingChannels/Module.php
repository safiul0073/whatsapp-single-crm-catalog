<?php

namespace App\Modules\MarketingChannels;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'marketing-channels';
    }

    public function permissions(): array
    {
        return [
            'admin' => ['marketing-channels.view' => 'View connected marketing channels'],
            'web' => ['marketing-channels.manage' => 'Manage connected marketing channels'],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Operations')->item('Channels', 'admin.marketing-channels.*', 'broadcast', 'marketing-channels.view', 34);
    }
}
