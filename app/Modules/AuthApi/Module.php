<?php

namespace App\Modules\AuthApi;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'auth-api';
    }
}
