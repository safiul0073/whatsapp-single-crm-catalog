<?php

namespace App\Modules\TikTok;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'tiktok';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'tiktok.settings.view' => 'View TikTok settings',
                'tiktok.settings.edit' => 'Edit TikTok settings',
            ],
            'web' => [
                'tiktok.manage' => 'Manage the TikTok channel',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('System')
            ->item(label: 'TikTok', route: 'admin.tiktok.*')
            ->icon('ph-tiktok-logo')
            ->permission('tiktok.settings.view')
            ->order(60);
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Channels')->item('TikTok', 'user.tiktok.*', 'tiktok-logo', 'tiktok.manage', 15);
    }
}
