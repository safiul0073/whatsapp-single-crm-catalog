<?php

namespace App\Modules\Sms;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'sms';
    }

    public function permissions(): array
    {
        return [
            'web' => ['sms.manage' => 'Manage SMS channel'],
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Channels')->item('SMS', 'user.sms.*', 'chat-text', null, 16);
    }
}
