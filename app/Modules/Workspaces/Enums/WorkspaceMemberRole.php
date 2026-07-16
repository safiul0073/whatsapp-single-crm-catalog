<?php

namespace App\Modules\Workspaces\Enums;

enum WorkspaceMemberRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Staff = 'staff';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrator => __('Administrator'),
            self::Manager => __('Manager'),
            self::Staff => __('Staff'),
        };
    }
}
