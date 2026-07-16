<?php

namespace App\Modules\Campaigns\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Sending = 'sending';
    case Completed = 'completed';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Scheduled => 'info',
            self::Queued => 'warning',
            self::Sending => 'warning',
            self::Completed => 'success',
            self::Paused => 'neutral',
            self::Cancelled => 'error',
            self::Failed => 'error',
        };
    }
}
