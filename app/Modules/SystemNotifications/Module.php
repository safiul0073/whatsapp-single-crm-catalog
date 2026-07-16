<?php

namespace App\Modules\SystemNotifications;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'system-notifications';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'system-notifications.view' => 'View system notifications',
                'system-notifications.send' => 'Send system notifications',
            ],
        ];
    }
}
