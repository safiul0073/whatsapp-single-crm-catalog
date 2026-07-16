<?php

namespace App\Modules\WhatsAppCloud\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudClient
{
    public function __construct(protected WhatsAppSettingsService $settings) {}

    public function phoneNumbers(string $businessAccountId, string $token): Response
    {
        return Http::withToken($token)->get($this->graphUrl("{$businessAccountId}/phone_numbers"));
    }

    public function sendTemplate(string $phoneNumberId, string $token, array $payload): Response
    {
        return Http::withToken($token)->post($this->endpoint($phoneNumberId, 'messages'), $payload);
    }

    public function sendMessage(string $phoneNumberId, string $token, array $payload): Response
    {
        return Http::withToken($token)->post($this->endpoint($phoneNumberId, 'messages'), $payload);
    }

    public function submitTemplate(string $businessAccountId, string $token, array $payload): Response
    {
        return Http::withToken($token)->post($this->graphUrl("{$businessAccountId}/message_templates"), $payload);
    }

    public function syncTemplates(string $businessAccountId, string $token): Response
    {
        return Http::withToken($token)->get($this->graphUrl("{$businessAccountId}/message_templates"));
    }

    public function uploadTemplateMedia(string $appId, string $token, string $path, string $fileName, string $mimeType, int $fileLength): Response
    {
        $session = Http::withToken($token)->asForm()->post($this->graphUrl("{$appId}/uploads"), [
            'file_name' => $fileName,
            'file_length' => $fileLength,
            'file_type' => $mimeType,
        ]);

        if (! $session->successful() || blank($session->json('id'))) {
            return $session;
        }

        return Http::withHeaders([
            'Authorization' => 'OAuth '.$token,
            'file_offset' => '0',
        ])->attach('file', fopen($path, 'r'), $fileName)->post($this->graphUrl((string) $session->json('id')));
    }

    public function exchangeEmbeddedSignupCode(string $code): Response
    {
        return Http::asForm()->get($this->graphUrl('oauth/access_token'), [
            'client_id' => $this->settings->get('whatsapp_meta_app_id'),
            'client_secret' => $this->settings->get('whatsapp_meta_app_secret'),
            'code' => $code,
        ]);
    }

    protected function endpoint(string $phoneNumberId, string $resource): string
    {
        return $this->graphUrl("{$phoneNumberId}/{$resource}");
    }

    protected function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.ltrim($path, '/');
    }
}
