<?php

namespace App\Modules\Telegram;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'telegram';
    }

    public function permissions(): array
    {
        return [
            'web' => ['telegram.manage' => 'Manage Telegram channel'],
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Channels')->item('Telegram', 'user.telegram.*', 'telegram-logo', null, 14);
    }
}
