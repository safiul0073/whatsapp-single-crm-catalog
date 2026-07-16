<?php

namespace App\Modules\Segments;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'segments';
    }

    public function userNavigation(NavigationBuilder $navigation): void {}
}
