<?php

namespace App\Modules\Frontend\Services;

class ActiveThemeResolver
{
    public function __construct(
        protected ThemeSettingsService $settings
    ) {}

    public function resolve(?string $requestedTheme = null): string
    {
        if ($requestedTheme && $this->settings->isEnabled($requestedTheme)) {
            return $requestedTheme;
        }

        return $this->settings->activeTheme();
    }
}
