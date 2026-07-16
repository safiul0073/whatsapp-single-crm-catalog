<?php

namespace App\Modules\Inbox\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
