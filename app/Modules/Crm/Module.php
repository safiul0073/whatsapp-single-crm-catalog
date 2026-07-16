<?php

namespace App\Modules\Crm;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'crm';
    }

    public function permissions(): array
    {
        return [
            'web' => [
                'crm.view' => 'View CRM',
                'crm.manage' => 'Manage CRM',
            ],
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Contacts')->item('CRM', 'user.crm.*', 'kanban', 'crm.view', 32);
    }
}
