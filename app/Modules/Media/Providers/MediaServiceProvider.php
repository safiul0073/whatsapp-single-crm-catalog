<?php

namespace App\Modules\Media\Providers;

use App\Modules\Media\Console\GenerateMediaVariants;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class MediaServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        parent::boot();
        if ($this->app->runningInConsole()) {
            $this->commands([GenerateMediaVariants::class]);
        }
    }
}
