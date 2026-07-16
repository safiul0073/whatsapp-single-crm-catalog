<?php

namespace App\Modules\SchedulerQueue\Jobs;

use App\Modules\SchedulerQueue\Services\ManagedSchedulerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunManagedSchedulerJob implements ShouldQueue
{
    use Queueable;

    public function handle(ManagedSchedulerService $scheduler): void
    {
        $scheduler->runDue();
    }
}
