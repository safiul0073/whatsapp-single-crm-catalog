<?php

namespace App\Modules\Crm\Enums;

enum CrmLeadSource: string
{
    case Manual = 'manual';
    case WhatsApp = 'whatsapp';
    case Campaign = 'campaign';
    case Automation = 'automation';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
