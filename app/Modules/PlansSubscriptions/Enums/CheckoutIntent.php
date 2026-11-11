<?php

namespace App\Modules\PlansSubscriptions\Enums;

enum CheckoutIntent: string
{
    case New = 'new';
    case Renew = 'renew';
    case Upgrade = 'upgrade';
    case Downgrade = 'downgrade';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
