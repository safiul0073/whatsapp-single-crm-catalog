<?php

namespace App\Modules\Leads;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'leads';
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Contacts')->item('Leads', 'user.leads.*', 'user-focus', 'leads.view', 31);
    }
}
