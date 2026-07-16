<?php

namespace App\Modules\Contacts\Enums;

enum ContactSource: string
{
    case Website = 'website';
    case Form = 'form';
    case Import = 'import';
    case Manual = 'manual';
    case AiGenerated = 'ai_generated';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
