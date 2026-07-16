<?php

namespace App\Modules\Crm\Enums;

enum CrmTaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
