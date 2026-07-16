<?php

namespace App\Modules\Automations;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'automations';
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Automation & AI')->item('Automations', 'user.automations.*', 'workflow', null, 50);
    }
}
