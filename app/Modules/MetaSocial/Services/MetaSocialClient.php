<?php

namespace App\Modules\MetaSocial\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaSocialClient
{
    public const INSTAGRAM_MEDIA_FIELDS = 'id,caption,media_type,media_url,thumbnail_url,permalink,like_count,comments_count,timestamp';

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

    public function instagramProfile(string $igUserId, string $token): Response
    {
        return Http::withToken($token)->get($this->graphUrl($igUserId), [
            'fields' => 'username,name,biography,profile_picture_url,followers_count,follows_count,media_count',
        ]);
    }

    public function instagramMedia(string $igUserId, string $token, int $limit = 12): Response
    {
        return Http::withToken($token)->get($this->graphUrl($igUserId.'/media'), [
            'fields' => self::INSTAGRAM_MEDIA_FIELDS,
            'limit' => $limit,
        ]);
    }

    /**
     * Business Discovery reads another public Business/Creator account through the connected one.
     */
    public function instagramBusinessDiscovery(string $igUserId, string $token, string $username, int $limit = 12): Response
    {
        $fields = 'username,name,biography,profile_picture_url,followers_count,follows_count,media_count,media.limit('.$limit.'){'.self::INSTAGRAM_MEDIA_FIELDS.'}';

        return Http::withToken($token)->get($this->graphUrl($igUserId), [
            'fields' => 'business_discovery.username('.$username.'){'.$fields.'}',
        ]);
    }

    public function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.ltrim($path, '/');
    }
}
