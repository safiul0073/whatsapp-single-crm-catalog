<?php

namespace App\Modules\Campaigns\Enums;

enum CampaignRecipientStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Replied = 'replied';
    case Failed = 'failed';
    case SkippedOptOut = 'skipped_opt_out';
    case SkippedBlocked = 'skipped_blocked';
    case SkippedInvalidPhone = 'skipped_invalid_phone';
    case SkippedInvalid = 'skipped_invalid';
    case SkippedPolicy = 'skipped_policy';
    case Opened = 'opened';
    case Clicked = 'clicked';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Queued => 'neutral',
            self::Sending => 'warning',
            self::Sent => 'info',
            self::Delivered => 'success',
            self::Opened => 'success',
            self::Read => 'success',
            self::Clicked => 'success',
            self::Replied => 'deep',
            self::Failed => 'error',
            self::SkippedOptOut, self::SkippedBlocked, self::SkippedInvalidPhone, self::SkippedInvalid, self::SkippedPolicy => 'neutral',
        };
    }
}
