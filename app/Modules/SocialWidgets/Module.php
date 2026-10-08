<?php

namespace App\Modules\SocialWidgets;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'social-widgets';
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Channels')->item('Widgets', 'user.social-widgets.library', 'squares-four', 'meta-social.manage', 15);
    }
}
