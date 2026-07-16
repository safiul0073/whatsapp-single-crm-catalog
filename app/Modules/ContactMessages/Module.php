<?php

namespace App\Modules\ContactMessages;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'contact-messages';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'contact-messages.view' => 'View Contact Messages',
                'contact-messages.manage' => 'Manage Contact Messages',
                'contact-messages.delete' => 'Delete Contact Messages',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Content')
            ->item('Contact Messages', 'admin.contact-messages.*', 'ph-envelope-simple', 'contact-messages.view', 62);
    }
}
