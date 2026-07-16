<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar as SpatiePermissionRegistrar;

class CacheBuster
{
    public function run(): void
    {
        foreach (['route:clear', 'config:clear', 'view:clear', 'event:clear'] as $command) {
            Artisan::call($command);
        }

        app(SpatiePermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('module:cache');
    }
}
