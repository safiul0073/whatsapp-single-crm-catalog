<?php

namespace App\Modules\Inbox\Enums;

enum MessageStatus: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Replied = 'replied';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
