<?php

namespace App\Modules\Workspaces\Enums;

enum WorkspaceStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
            self::Archived => __('Archived'),
        };
    }
}
