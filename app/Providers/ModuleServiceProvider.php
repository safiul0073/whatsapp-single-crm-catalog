<?php

namespace App\Providers;

use App\Modules\Shared\Support\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn ($app): ModuleRegistry => new ModuleRegistry($app));
        $this->app->make(ModuleRegistry::class)->registerModules();
    }

    public function boot(): void
    {
        //
    }
}
