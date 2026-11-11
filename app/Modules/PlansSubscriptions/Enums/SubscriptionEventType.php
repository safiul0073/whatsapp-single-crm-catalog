<?php

namespace App\Modules\PlansSubscriptions\Enums;

enum SubscriptionEventType: string
{
    case Activated = 'activated';
    case Renewed = 'renewed';
    case Upgraded = 'upgraded';
    case DowngradeScheduled = 'downgrade_scheduled';
    case Downgraded = 'downgraded';
    case Cancelled = 'cancelled';
    case Resumed = 'resumed';
    case Expired = 'expired';
    case Extended = 'extended';
    case AdminChanged = 'admin_changed';
    case UsageReset = 'usage_reset';
    case LimitAlert = 'limit_alert';

    public function label(): string
    {
        return match ($this) {
            self::Activated => __('Activated'),
            self::Renewed => __('Renewed'),
            self::Upgraded => __('Upgraded'),
            self::DowngradeScheduled => __('Downgrade scheduled'),
            self::Downgraded => __('Downgraded'),
            self::Cancelled => __('Cancelled'),
            self::Resumed => __('Resumed'),
            self::Expired => __('Expired'),
            self::Extended => __('Extended'),
            self::AdminChanged => __('Changed by admin'),
            self::UsageReset => __('Usage reset'),
            self::LimitAlert => __('Usage alert'),
        };
    }
}
