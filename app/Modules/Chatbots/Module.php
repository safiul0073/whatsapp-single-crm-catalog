<?php

namespace App\Modules\Chatbots;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'chatbots';
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Automation & AI')
            ->item('Chatbots', 'user.chatbots.*', 'bot', null, 51)
            ->item('Website Widgets', 'user.chatbots.widgets.*', 'browser', null, 52);
    }
}
