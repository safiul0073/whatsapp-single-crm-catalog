<?php

namespace App\Modules\MetaSocial\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaSocialClient
{
    public function __construct(protected MetaSocialSettingsService $settings) {}

    public function exchangeEmbeddedSignupCode(string $code): Response
    {
        return Http::asForm()->get($this->graphUrl('oauth/access_token'), [
            'client_id' => $this->settings->get('meta_social_app_id'),
            'client_secret' => $this->settings->get('meta_social_app_secret'),
            'code' => $code,
        ]);
    }

    public function pageAccounts(string $token): Response
    {
        return Http::withToken($token)->get($this->graphUrl('me/accounts'), [
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name}',
        ]);
    }

    public function account(string $accountId, string $token, array $fields = ['id', 'name']): Response
    {
        return Http::withToken($token)->get($this->graphUrl($accountId), [
            'fields' => implode(',', $fields),
        ]);
    }

    public function sendMessengerMessage(string $pageId, string $token, array $payload): Response
    {
        return Http::withToken($token)->post($this->graphUrl($pageId.'/messages'), $payload);
    }

    public function sendInstagramMessage(string $igUserId, string $token, array $payload): Response
    {
        return Http::withToken($token)->post($this->graphUrl($igUserId.'/messages'), $payload);
    }

    public function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.ltrim($path, '/');
    }
}
