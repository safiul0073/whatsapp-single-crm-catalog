<?php

namespace App\Modules\Contacts;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'contacts';
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Contacts')->item('Contacts', 'user.contacts.*', 'users', null, 30);
    }
}
