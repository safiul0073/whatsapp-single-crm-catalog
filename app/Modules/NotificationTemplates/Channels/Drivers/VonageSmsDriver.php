<?php

namespace App\Modules\NotificationTemplates\Channels\Drivers;

use RuntimeException;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;

class VonageSmsDriver implements SmsDriverInterface
{
    public function send(string $to, string $message): void
    {
        $apiKey = setting('vonage_api_key');
        $apiSecret = setting('vonage_api_secret');
        $from = setting('sms_from_number');

        if (! $apiKey || ! $apiSecret) {
            throw new RuntimeException(
                'Vonage credentials are not configured. Set them in Settings → Notifications.'
            );
        }

        $credentials = new Basic($apiKey, $apiSecret);
        $client = new Client($credentials);

        $client->sms()->send(
            new SMS($to, $from ?: 'App', $message)
        );
    }
}
