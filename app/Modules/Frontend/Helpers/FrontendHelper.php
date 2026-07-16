<?php

use App\Modules\Frontend\Services\ActiveThemeResolver;

if (! function_exists('frontend_active_theme')) {
    function frontend_active_theme(): string
    {
        return app(ActiveThemeResolver::class)->resolve();
    }
}
