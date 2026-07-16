<?php

namespace App\Modules\Email;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'email';
    }

    public function permissions(): array
    {
        return [
            'web' => ['email.manage' => 'Manage Email channel'],
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Channels')->item('Email', 'user.email.*', 'envelope-simple', null, 15);
    }
}
