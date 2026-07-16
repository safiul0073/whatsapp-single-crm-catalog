<?php

namespace App\Modules\Shared;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'shared';
    }
}
