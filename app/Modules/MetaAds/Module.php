<?php

namespace App\Modules\MetaAds;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'meta-ads';
    }

    public function permissions(): array
    {
        return [
            'web' => [
                'meta-ads.view' => 'View Meta Ads performance and ad-started conversations',
                'meta-ads.manage' => 'Create, start, pause and delete Facebook and Instagram ads',
            ],
        ];
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Broadcasting')->item('Meta Ads', 'user.meta-ads.*', 'megaphone', 'meta-ads.view', 22);
    }
}
