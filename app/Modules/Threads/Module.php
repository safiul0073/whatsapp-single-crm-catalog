<?php

namespace App\Modules\Threads;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'threads';
    }

    public function permissions(): array
    {
        return [
            'web' => ['threads.manage' => 'Manage Threads channel'],
        ];
    }
}
