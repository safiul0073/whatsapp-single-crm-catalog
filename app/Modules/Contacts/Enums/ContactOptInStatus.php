<?php

namespace App\Modules\Contacts\Enums;

enum ContactOptInStatus: string
{
    case Unknown = 'unknown';
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
