<?php

namespace App\Enums;

use App\Modules\NotificationTemplates\Models\NotificationTemplate;

enum NotificationTemplateSlug: string
{
    case WELCOME = 'welcome';
    case PASSWORD_CHANGED = 'password-changed';
    case SUBSCRIPTION_EXPIRING_SOON = 'subscription-expiring-soon';
    case SUBSCRIPTION_EXPIRED = 'subscription-expired';

    public function template(): ?NotificationTemplate
    {
        return NotificationTemplate::findBySlug($this->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::WELCOME => 'Welcome',
            self::PASSWORD_CHANGED => 'Password Changed',
            self::SUBSCRIPTION_EXPIRING_SOON => 'Subscription Expiring Soon',
            self::SUBSCRIPTION_EXPIRED => 'Subscription Expired',
        };
    }
}
