<?php

namespace App\Modules\Shipping;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'shipping';
    }

    public function permissions(): array
    {
        return ['web' => ['shipping.view' => 'View shipping settings', 'shipping.manage' => 'Manage shipping settings']];
    }

    public function policies(): array
    {
        return [];
    }
}
