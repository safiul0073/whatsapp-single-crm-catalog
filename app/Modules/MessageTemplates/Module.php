<?php

namespace App\Modules\MessageTemplates;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'message-templates';
    }

    public function permissions(): array
    {
        return [
            'web' => [
                'templates.manage' => 'Manage message templates',
            ],
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Messaging')->item('Templates', 'user.message-templates.*', 'file-text', null, 20);
    }
}
