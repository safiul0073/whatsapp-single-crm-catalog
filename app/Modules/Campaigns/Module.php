<?php

namespace App\Modules\Campaigns;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Policies\CampaignPolicy;
use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'campaigns';
    }

    public function policies(): array
    {
        return [
            Campaign::class => CampaignPolicy::class,
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Broadcasting')->item('Campaigns', 'user.campaigns.*', 'send', null, 40);
    }
}
