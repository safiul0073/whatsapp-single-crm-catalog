<?php

namespace App\Modules\WhatsAppCloud;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'whatsapp-cloud';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'whatsapp-cloud.settings.view' => 'View WhatsApp Cloud settings',
                'whatsapp-cloud.settings.edit' => 'Edit WhatsApp Cloud settings',
            ],
            'web' => ['whatsapp-cloud.manage' => 'Manage WhatsApp Cloud API channel'],
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Inbox')->item('Channel Setup', 'user.whatsapp-cloud.*', 'settings', null, 12);
    }
}
