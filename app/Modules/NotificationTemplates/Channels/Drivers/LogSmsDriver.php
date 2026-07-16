<?php

namespace App\Modules\NotificationTemplates\Channels\Drivers;

use Illuminate\Support\Facades\Log;

class LogSmsDriver implements SmsDriverInterface
{
    public function send(string $to, string $message): void
    {
        Log::channel('single')->info("SMS to {$to}: {$message}");
    }
}
