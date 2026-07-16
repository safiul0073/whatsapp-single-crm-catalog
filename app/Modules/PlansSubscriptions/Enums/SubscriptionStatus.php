<?php

namespace App\Modules\PlansSubscriptions\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
