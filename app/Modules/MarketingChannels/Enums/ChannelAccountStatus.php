<?php

namespace App\Modules\MarketingChannels\Enums;

enum ChannelAccountStatus: string
{
    case Draft = 'draft';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';
    case Suspended = 'suspended';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
