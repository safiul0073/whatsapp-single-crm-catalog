<?php

namespace App\Modules\PlaceApiSettings\Providers;

use App\Modules\PlaceApiSettings\Services\PlaceApiSettingsService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class PlaceApiSettingsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(PlaceApiSettingsService::class);
    }
}
