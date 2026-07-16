<?php

use App\Modules\SchedulerQueue\Jobs\RunManagedSchedulerJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new RunManagedSchedulerJob)->everyMinute();
