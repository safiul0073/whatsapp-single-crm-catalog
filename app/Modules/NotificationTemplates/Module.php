<?php

namespace App\Modules\NotificationTemplates;

use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Policies\NotificationLogPolicy;
use App\Modules\NotificationTemplates\Policies\NotificationTemplatePolicy;
use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'notification-templates';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'notification-templates.view' => 'View notification templates',
                'notification-templates.edit' => 'Edit notification templates',
                'notification-logs.view' => 'View notification logs',
            ],
        ];
    }

    public function policies(): array
    {
        return [
            NotificationTemplate::class => NotificationTemplatePolicy::class,
            NotificationLog::class => NotificationLogPolicy::class,
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        //
    }
}
