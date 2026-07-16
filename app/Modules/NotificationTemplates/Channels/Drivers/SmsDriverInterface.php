<?php

namespace App\Modules\NotificationTemplates\Channels\Drivers;

interface SmsDriverInterface
{
    /**
     * Send an SMS message.
     */
    public function send(string $to, string $message): void;
}
