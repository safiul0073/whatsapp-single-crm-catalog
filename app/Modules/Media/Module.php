<?php

namespace App\Modules\Media;

use App\Modules\Shared\Support\BasePanelModule;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'media';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'media.view' => 'View media',
                'media.create' => 'Create media',
                'media.delete' => 'Delete media',
            ],
        ];
    }
}
