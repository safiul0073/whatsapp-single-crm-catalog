<?php

namespace App\Modules\AuditLog;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'audit-logs';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'audit-logs.view' => 'View audit logs',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('System')
            ->item(label: 'Audit Logs', route: 'admin.audit-logs.*')
            ->icon('ph-clipboard-text')
            ->permission('audit-logs.view')
            ->order(110);
    }
}
