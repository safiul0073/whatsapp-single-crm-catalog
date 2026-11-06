<?php

namespace App\Modules\TikTok\Services;

use Illuminate\Http\Client\PendingRequest;
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
     * The creator's nickname, allowed privacy levels, disabled interactions and longest allowed video
     * (Content Posting API, video.publish). TikTok requires fresh values on every "Post to TikTok" screen.
     */
    public function creatorInfo(string $accessToken): Response
    {
        return $this->json($accessToken)->post(self::API_URL.'post/publish/creator_info/query/', (object) []);
    }

    /**
     * Starts a direct post (video.publish) with a file upload.
     *
     * @param  array<string, mixed>  $postInfo
     * @param  array{video_size: int, chunk_size: int, total_chunk_count: int}  $upload
     */
    public function initDirectPost(string $accessToken, array $postInfo, array $upload): Response
    {
        return $this->json($accessToken)->post(self::API_URL.'post/publish/video/init/', [
            'post_info' => $postInfo,
            'source_info' => ['source' => 'FILE_UPLOAD'] + $upload,
        ]);
    }

    /**
     * Sends the video to the creator's TikTok inbox as a draft to finish in the app (video.upload).
     *
     * @param  array{video_size: int, chunk_size: int, total_chunk_count: int}  $upload
     */
    public function initInboxUpload(string $accessToken, array $upload): Response
    {
        return $this->json($accessToken)->post(self::API_URL.'post/publish/inbox/video/init/', [
            'source_info' => ['source' => 'FILE_UPLOAD'] + $upload,
        ]);
    }

    /**
     * Uploads one chunk of the video to the upload_url TikTok returned.
     */
    public function uploadChunk(string $uploadUrl, string $bytes, int $firstByte, int $totalBytes, string $mimeType): Response
    {
        $lastByte = $firstByte + strlen($bytes) - 1;

        return Http::withHeaders([
            'Content-Type' => $mimeType,
            'Content-Range' => 'bytes '.$firstByte.'-'.$lastByte.'/'.$totalBytes,
        ])->connectTimeout(10)->timeout(300)->withBody($bytes, $mimeType)->put($uploadUrl);
    }

    public function postStatus(string $accessToken, string $publishId): Response
    {
        return $this->json($accessToken)->post(self::API_URL.'post/publish/status/fetch/', ['publish_id' => $publishId]);
    }

    protected function json(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->asJson()
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->connectTimeout(5)
            ->timeout(30);
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
