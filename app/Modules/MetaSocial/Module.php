<?php

namespace App\Modules\MetaSocial;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'meta-social';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'meta-social.settings.view' => 'View Meta social settings',
                'meta-social.settings.edit' => 'Edit Meta social settings',
            ],
            'web' => [
                'meta-social.manage' => 'Manage Messenger and Instagram channels',
            ],
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void {}
}
