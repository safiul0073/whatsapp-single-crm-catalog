<?php

namespace App\Modules\SchedulerQueue;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'scheduler-queue';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'scheduler-queues.view' => 'View scheduler and queue monitor',
                'scheduler-queues.edit' => 'Edit scheduler entries',
                'scheduler-queues.run' => 'Run scheduler entries',
                'scheduler-queues.manage' => 'Manage queue jobs',
            ],
        ];
    }
}
