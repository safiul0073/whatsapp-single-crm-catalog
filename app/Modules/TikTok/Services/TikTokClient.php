<?php

namespace App\Modules\TikTok\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Login Kit (v2) calls. Access tokens last 24 hours and refresh tokens can rotate.
 */
class TikTokClient
{
    public const AUTHORIZE_URL = 'https://www.tiktok.com/v2/auth/authorize/';

    public const API_URL = 'https://open.tiktokapis.com/v2/';

    public function __construct(protected TikTokSettingsService $settings) {}

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_key' => $this->settings->clientKey(),
            'scope' => $this->settings->scopes(),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): Response
    {
        return $this->token([
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function refresh(string $refreshToken): Response
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function userInfo(string $accessToken): Response
    {
        return Http::withToken($accessToken)
            ->connectTimeout(5)
            ->timeout(20)
            ->get(self::API_URL.'user/info/', ['fields' => 'open_id,union_id,avatar_url,display_name']);
    }

    /**
     * @param  array<string, string>  $params
     */
    protected function token(array $params): Response
    {
        return Http::asForm()
            ->connectTimeout(5)
            ->timeout(20)
            ->post(self::API_URL.'oauth/token/', $params + [
                'client_key' => $this->settings->clientKey(),
                'client_secret' => $this->settings->clientSecret(),
            ]);
    }
}
