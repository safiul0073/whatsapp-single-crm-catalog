<?php

namespace App\Modules\Email\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailProvider implements MarketingChannelDriver
{
    public function __construct(protected EmailGatewayInterface $gateway) {}

    public function provider(): string
    {
        return 'email';
    }

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
    {
        $to = (string) ($recipient['to'] ?? $recipient['email'] ?? '');
        $subject = (string) ($payload['subject'] ?? '');
        $htmlBody = (string) ($payload['html_body'] ?? '');
        $textBody = (string) ($payload['text_body'] ?? '');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'error' => 'Invalid recipient email address.',
            ];
        }

        $config = $this->mailConfig($account);

        if ($payload['append_unsubscribe'] ?? false) {
            $unsubscribeUrl = $payload['unsubscribe_url'] ?? '#';
            $htmlBody .= $this->unsubscribeFooterHtml($unsubscribeUrl);
            $textBody .= $this->unsubscribeFooterText($unsubscribeUrl);
        }

        $result = $this->gateway->send($to, $subject, $htmlBody, $textBody, $config);
        $result['provider'] = $this->provider();

        return $result;
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
            'from_address' => $account->provider_display_id,
        ];
    }

    public function testConnection(ChannelAccount $account): array
    {
        $result = $this->gateway->test($this->mailConfig($account));
        $result['provider'] = $this->provider();

        return $result;
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
                'message_type' => 'Email campaigns support custom content or automation flows.',
            ]);
        }

        if (blank($campaign->message_subject)) {
            throw ValidationException::withMessages([
                'message_subject' => 'Email campaigns require a subject.',
            ]);
        }

        if (blank($campaign->message_body)) {
            throw ValidationException::withMessages([
                'message_body' => 'Email campaigns require a message body.',
            ]);
        }
    }

    /**
     * Build a mail configuration array from the channel account credentials.
     */
    public function mailConfig(ChannelAccount $account): array
    {
        $credentials = $account->credentials ?? [];

        return [
            'mailer' => $credentials['mail_mailer'] ?? 'log',
            'from_address' => $account->provider_display_id,
            'from_name' => $credentials['mail_from_name'] ?? config('mail.from.name'),
            'host' => $credentials['mail_host'] ?? null,
            'port' => $credentials['mail_port'] ?? null,
            'encryption' => $credentials['mail_encryption'] ?? 'tls',
            'username' => $credentials['mail_username'] ?? null,
            'password' => $credentials['mail_password'] ?? null,
            'mailgun_domain' => $credentials['mailgun_domain'] ?? null,
            'mailgun_secret' => $credentials['mailgun_secret'] ?? null,
            'mailgun_endpoint' => $credentials['mailgun_endpoint'] ?? 'api.mailgun.net',
        ];
    }

    protected function unsubscribeFooterHtml(string $url): string
    {
        return '<hr style="border:0;border-top:1px solid #e5e5e5;margin:20px 0;"><p style="font-size:12px;color:#888;">If you no longer wish to receive these emails, <a href="'.e($url).'">unsubscribe here</a>.</p>';
    }

    protected function unsubscribeFooterText(string $url): string
    {
        return "\n\n---\nIf you no longer wish to receive these emails, unsubscribe here: {$url}";
    }
}
