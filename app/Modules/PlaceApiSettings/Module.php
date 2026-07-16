<?php

namespace App\Modules\PlaceApiSettings;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'place-api-settings';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'place-api-settings.view' => 'View Place API settings',
                'place-api-settings.edit' => 'Edit Place API settings',
            ],
        ];
    }
}
