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

    public function exchangeLongLivedToken(string $token): Response
    {
        return Http::asForm()->get($this->graphUrl('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->settings->get('meta_social_app_id'),
            'client_secret' => $this->settings->get('meta_social_app_secret'),
            'fb_exchange_token' => $token,
        ]);
    }

    public function pageAccounts(string $token): Response
    {
        return Http::withToken($token)->get($this->graphUrl('me/accounts'), [
            'fields' => 'id,name,access_token,tasks,instagram_business_account{id,username,name}',
        ]);
    }

    public function subscribePage(string $pageId, string $pageToken, string $fields): Response
    {
        return Http::withToken($pageToken)->asForm()->post($this->graphUrl($pageId.'/subscribed_apps'), [
            'subscribed_fields' => $fields,
        ]);
    }

    public function pageSubscriptions(string $pageId, string $pageToken): Response
    {
        return Http::withToken($pageToken)->get($this->graphUrl($pageId.'/subscribed_apps'));
    }

    public function account(string $accountId, string $token, array $fields = ['id', 'name']): Response
    {
        return Http::withToken($token)->get($this->graphUrl($accountId), [
            'fields' => implode(',', $fields),
        ]);
    }

    /**
     * Reads the name and photo of someone who messaged the Page or Instagram account
     * (needs the Business Asset User Profile Access feature).
     */
    public function userProfile(string $userId, string $token, string $provider): Response
    {
        return Http::withToken($token)->timeout(10)->get($this->graphUrl($userId), [
            'fields' => $provider === 'instagram' ? 'name,username,profile_pic' : 'first_name,last_name,profile_pic',
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

    public function replyToInstagramComment(string $commentId, string $token, string $message): Response
    {
        return Http::withToken($token)->post($this->graphUrl($commentId.'/replies'), ['message' => $message]);
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

    /**
     * A text or link post on the Page feed (pages_manage_posts).
     */
    public function publishPagePost(string $pageId, string $pageToken, string $message, ?string $link = null): Response
    {
        return Http::withToken($pageToken)->asForm()->timeout(30)->post($this->graphUrl($pageId.'/feed'), array_filter([
            'message' => $message,
            'link' => $link,
        ]));
    }

    /**
     * A photo post on the Page (pages_manage_posts).
     */
    public function publishPagePhoto(string $pageId, string $pageToken, string $imageUrl, string $caption): Response
    {
        return Http::withToken($pageToken)->asForm()->timeout(60)->post($this->graphUrl($pageId.'/photos'), [
            'url' => $imageUrl,
            'caption' => $caption,
            'published' => 'true',
        ]);
    }

    public function deletePagePost(string $postId, string $pageToken): Response
    {
        return Http::withToken($pageToken)->timeout(30)->delete($this->graphUrl($postId));
    }

    public function pagePublishedPosts(string $pageId, string $pageToken, int $limit = 12): Response
    {
        return Http::withToken($pageToken)->timeout(20)->get($this->graphUrl($pageId.'/published_posts'), [
            'fields' => 'id,message,full_picture,permalink_url,created_time',
            'limit' => $limit,
        ]);
    }

    /**
     * Step 1 of an Instagram post: a media container (instagram_content_publish).
     */
    public function createInstagramMedia(string $igUserId, string $token, string $imageUrl, string $caption): Response
    {
        return Http::withToken($token)->asForm()->timeout(60)->post($this->graphUrl($igUserId.'/media'), [
            'image_url' => $imageUrl,
            'caption' => $caption,
        ]);
    }

    /**
     * Step 2 of an Instagram post: publish the container.
     */
    public function publishInstagramMedia(string $igUserId, string $token, string $creationId): Response
    {
        return Http::withToken($token)->asForm()->timeout(60)->post($this->graphUrl($igUserId.'/media_publish'), [
            'creation_id' => $creationId,
        ]);
    }

    public function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->settings->graphApiVersion().'/'.ltrim($path, '/');
    }
}
