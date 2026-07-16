<?php

namespace App\Modules\Inbox;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'inbox';
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Inbox')->item('Inbox', 'user.inbox.*', 'message-circle', null, 10);
    }
}
