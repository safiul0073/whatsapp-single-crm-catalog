<?php

namespace App\Modules\Settings;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'settings';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'settings.view' => 'View settings',
                'settings.edit' => 'Edit settings',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('System')
            ->item(label: 'Settings', route: 'admin.settings.*')
            ->icon('ph-gear')
            ->permission('settings.view')
            ->children([
                ['label' => 'General Settings', 'route' => 'admin.settings.index'],
                ['label' => 'WhatsApp Cloud', 'route' => 'admin.whatsapp-cloud.settings.*', 'permission' => 'whatsapp-cloud.settings.view'],
                ['label' => 'Meta Social', 'route' => 'admin.meta-social.settings.*', 'permission' => 'meta-social.settings.view'],
                ['label' => 'AI Settings', 'route' => 'admin.ai-settings.*', 'permission' => 'ai-settings.view'],
                ['label' => 'Vector Database', 'route' => 'admin.ai-settings.vector-database.*', 'permission' => 'ai-settings.view'],
                ['label' => 'Place API Settings', 'route' => 'admin.place-api-settings.*', 'permission' => 'place-api-settings.view'],
                ['label' => 'Payment Gateways', 'route' => 'admin.payment-gateway-settings.*', 'permission' => 'payment-gateway-settings.view'],
                ['label' => 'Currencies', 'route' => 'admin.currencies.*', 'permission' => 'currencies.view'],
                ['label' => 'Languages', 'route' => 'admin.languages.*', 'permission' => 'languages.view'],
                ['label' => 'Media Library', 'route' => 'admin.media.*', 'permission' => 'media.view'],
                ['label' => 'Notifications', 'route' => 'admin.notification-templates.*', 'permission' => 'notification-templates.view'],
                ['label' => 'Scheduler & Queues', 'route' => 'admin.scheduler-queues.*', 'permission' => 'scheduler-queues.view'],
            ])
            ->order(130);
    }
}
