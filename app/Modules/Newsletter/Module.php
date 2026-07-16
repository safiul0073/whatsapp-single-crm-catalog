<?php

namespace App\Modules\Newsletter;

use App\Modules\Newsletter\Models\Subscriber;
use App\Modules\Newsletter\Policies\SubscriberPolicy;
use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'newsletter';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'newsletter.view' => 'View Subscribers',
                'newsletter.edit' => 'Manage Subscribers',
                'newsletter.delete' => 'Delete Subscribers',
                'newsletter.send' => 'Send Newsletters',
            ],
        ];
    }

    public function policies(): array
    {
        return [
            Subscriber::class => SubscriberPolicy::class,
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Content')
            ->item('Newsletter', 'admin.subscribers.*', 'ph-envelope', 'newsletter.view', 60, [
                ['label' => 'Subscribers', 'route' => 'admin.subscribers.index'],
                ['label' => 'Send Newsletter', 'route' => 'admin.subscribers.send.create'],
            ]);
    }
}
