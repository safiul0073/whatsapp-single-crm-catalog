<?php

namespace App\Modules\Sms\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\NotificationTemplates\Channels\Drivers\LogSmsDriver;
use App\Modules\NotificationTemplates\Channels\Drivers\SmsDriverInterface;
use App\Modules\NotificationTemplates\Channels\Drivers\TwilioSmsDriver;
use App\Modules\NotificationTemplates\Channels\Drivers\VonageSmsDriver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SmsProvider implements MarketingChannelDriver
{
    public function provider(): string
    {
        return 'sms';
    }

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
    {
        $to = (string) ($recipient['to'] ?? $recipient['phone'] ?? '');
        $body = (string) ($payload['body'] ?? '');

        if (! $this->isValidE164($to)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'Invalid recipient phone number.',
            ];
        }

        if (blank($body)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'SMS body is required.',
            ];
        }

        $driver = $this->resolveDriver($account);

        try {
            $driver->send($to, $body);

            return [
                'ok' => true,
                'provider' => $this->provider(),
                'provider_message_id' => null,
                'status' => 'sent',
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider' => $this->provider(),
                'provider_message_id' => null,
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function verifyWebhook(Request $request, ChannelAccount $account): bool
    {
        return false;
    }

    public function handleWebhook(Request $request, ChannelAccount $account): array
    {
        return ['ok' => true];
    }

    public function syncTemplates(ChannelAccount $account): array
    {
        return ['ok' => true, 'synced' => 0];
    }

    public function getHealthStatus(ChannelAccount $account): array
    {
        return [
            'provider' => $this->provider(),
            'status' => $account->status,
            'connected' => $account->status?->value === 'connected',
            'gateway' => $account->credential('sms_provider'),
        ];
    }

    public function testConnection(ChannelAccount $account): array
    {
        $gateway = $account->credential('sms_provider', 'log');

        if ($gateway === 'log') {
            return ['ok' => true, 'provider' => $this->provider()];
        }

        if ($gateway === 'twilio') {
            if (blank($account->credential('twilio_sid')) || blank($account->credential('twilio_auth_token'))) {
                return ['ok' => false, 'provider' => $this->provider(), 'error' => 'Twilio SID and auth token are required.'];
            }
        }

        if ($gateway === 'vonage') {
            if (blank($account->credential('vonage_api_key')) || blank($account->credential('vonage_api_secret'))) {
                return ['ok' => false, 'provider' => $this->provider(), 'error' => 'Vonage API key and secret are required.'];
            }
        }

        return ['ok' => true, 'provider' => $this->provider()];
    }

    public function processWebhook(ChannelAccount $account, array $payload): array
    {
        return ['ok' => true, 'events' => []];
    }

    public function getCapabilities(): array
    {
        return ['Campaigns'];
    }

    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
    {
        if ($campaign->message_type === 'automation') {
            return;
        }

        if ($campaign->message_type !== 'custom') {
            throw ValidationException::withMessages([
                'message_type' => 'SMS campaigns support custom content or automation flows.',
            ]);
        }

        if (blank($campaign->message_body)) {
            throw ValidationException::withMessages([
                'message_body' => 'SMS campaigns require a message body.',
            ]);
        }
    }

    public function segmentCount(string $body): array
    {
        $length = mb_strlen($body);
        $segmentLength = $length <= 160 ? 160 : 153;
        $segments = (int) ceil($length / $segmentLength);

        return ['length' => $length, 'segments' => max(1, $segments)];
    }

    protected function resolveDriver(ChannelAccount $account): SmsDriverInterface
    {
        $gateway = $account->credential('sms_provider', 'log');

        return match ($gateway) {
            'twilio' => app(TwilioSmsDriver::class),
            'vonage' => app(VonageSmsDriver::class),
            default => app(LogSmsDriver::class),
        };
    }

    protected function isValidE164(string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1;
    }
}
