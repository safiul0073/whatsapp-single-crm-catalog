<?php

namespace App\Modules\Commerce\Providers;

use App\Modules\Commerce\Jobs\ReconcileMetaCatalogsJob;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use Illuminate\Console\Scheduling\Schedule;

class CommerceServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        //
    }

    protected function bootModule(array $module): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->afterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->job(new ReconcileMetaCatalogsJob)->hourly()->withoutOverlapping());
        }
    }
}
