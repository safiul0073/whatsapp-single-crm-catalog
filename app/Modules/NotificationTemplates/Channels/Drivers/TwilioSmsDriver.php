<?php

namespace App\Modules\NotificationTemplates\Channels\Drivers;

use RuntimeException;
use Twilio\Rest\Client;

class TwilioSmsDriver implements SmsDriverInterface
{
    public function send(string $to, string $message): void
    {
        $sid = setting('twilio_sid');
        $token = setting('twilio_auth_token');
        $from = setting('sms_from_number');

        if (! $sid || ! $token) {
            throw new RuntimeException(
                'Twilio credentials are not configured. Set them in Settings → Notifications.'
            );
        }

        if (! $from) {
            throw new RuntimeException(
                'SMS From Number is not configured. Set it in Settings → Notifications.'
            );
        }

        $client = new Client($sid, $token);

        $client->messages->create($to, [
            'from' => $from,
            'body' => $message,
        ]);
    }
}
