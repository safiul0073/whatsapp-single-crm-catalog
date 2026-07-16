<?php

namespace App\Modules\LoginActivity;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'login-activity';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'login-activity.view' => 'View login activity',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('System')
            ->item(label: 'Login Activity', route: 'admin.login-activity.*')
            ->icon('ph-sign-in')
            ->permission('login-activity.view')
            ->order(115);
    }
}
