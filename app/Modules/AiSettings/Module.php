<?php

namespace App\Modules\AiSettings;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'ai-settings';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'ai-settings.view' => 'View AI settings',
                'ai-settings.edit' => 'Edit AI settings',
                'ai-usage.view' => 'View AI usage logs',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Main Menu')
            ->item(label: 'AI Usage', route: 'admin.ai-usage.*')
            ->icon('ph-chart-bar')
            ->permission('ai-usage.view')
            ->order(10);
    }
}
