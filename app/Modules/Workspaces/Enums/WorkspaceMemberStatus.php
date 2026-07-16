<?php

namespace App\Modules\Workspaces\Enums;

enum WorkspaceMemberStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Invited => __('Invited'),
            self::Suspended => __('Suspended'),
        };
    }
}
