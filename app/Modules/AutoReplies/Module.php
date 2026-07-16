<?php

namespace App\Modules\AutoReplies;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'auto-replies';
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Messaging')->item('Auto Replies', 'user.auto-replies.*', 'reply', null, 21);
    }
}
