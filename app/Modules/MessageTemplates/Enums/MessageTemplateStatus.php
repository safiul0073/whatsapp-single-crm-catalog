<?php

namespace App\Modules\MessageTemplates\Enums;

enum MessageTemplateStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case Disabled = 'disabled';
    case Failed = 'failed';
    case InAppeal = 'in_appeal';
    case PendingDeletion = 'pending_deletion';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
