<?php

namespace App\Modules\KnowledgeBases;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'knowledge-bases';
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->group('Automation & AI')
            ->item('Knowledge Bases', 'user.knowledge-bases.*', 'books', null, 52);
    }
}
