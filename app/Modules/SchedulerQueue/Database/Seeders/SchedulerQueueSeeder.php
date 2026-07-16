<?php

namespace App\Modules\SchedulerQueue\Database\Seeders;

use App\Modules\SchedulerQueue\Services\ManagedSchedulerService;
use Illuminate\Database\Seeder;

class SchedulerQueueSeeder extends Seeder
{
    public function run(): void
    {
        app(ManagedSchedulerService::class)->syncRegisteredEntries();
    }
}
