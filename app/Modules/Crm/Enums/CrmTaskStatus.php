<?php

namespace App\Modules\Crm\Enums;

enum CrmTaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
