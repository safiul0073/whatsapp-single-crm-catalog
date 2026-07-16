<?php

namespace App\Modules\Crm\Enums;

enum CrmLeadStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
