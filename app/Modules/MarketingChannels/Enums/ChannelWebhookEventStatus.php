<?php

namespace App\Modules\MarketingChannels\Enums;

enum ChannelWebhookEventStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
