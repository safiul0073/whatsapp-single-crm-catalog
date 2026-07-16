<?php

namespace App\Modules\Contacts\Enums;

enum ContactImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
