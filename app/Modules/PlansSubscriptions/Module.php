<?php

namespace App\Modules\PlansSubscriptions;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'plans-subscriptions';
    }

    public function permissions(): array
    {
        return ['admin' => ['plans.view' => 'View plans', 'plans.manage' => 'Manage plans', 'subscriptions.view' => 'View subscriptions']];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Billing')
            ->item('Plans', 'admin.plans.*', 'ph-stack', 'plans.view', 30)
            ->item('Subscriptions', 'admin.subscriptions.*', 'ph-repeat', 'subscriptions.view', 31);
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Account')->item('Subscription', 'user.subscription.*', 'credit-card', null, 110);
    }
}
