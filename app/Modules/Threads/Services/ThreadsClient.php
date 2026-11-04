<?php

namespace App\Modules\Threads\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ThreadsClient
{
    public function account(string $threadsUserId, string $token): Response
    {
        return Http::withToken($token)->timeout(30)->get($this->graphUrl($threadsUserId), [
            'fields' => 'id,username,name',
        ]);
    }

    public function createTextPost(string $threadsUserId, string $token, string $text, ?string $replyToId = null): Response
    {
        return Http::withToken($token)->timeout(30)->post($this->graphUrl($threadsUserId.'/threads'), array_filter([
            'media_type' => 'TEXT',
            'text' => $text,
            'reply_to_id' => $replyToId,
        ], fn ($value): bool => filled($value)));
    }

    public function publishPost(string $threadsUserId, string $token, string $creationId): Response
    {
        return Http::withToken($token)->timeout(30)->post($this->graphUrl($threadsUserId.'/threads_publish'), [
            'creation_id' => $creationId,
        ]);
    }

    public function authorizeUrl(string $appId, string $redirectUri, string $state): string
    {
        return rtrim((string) config('threads.authorize_url', 'https://threads.net/oauth/authorize'), '?').'?'.http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => 'threads_basic,threads_content_publish,threads_manage_replies,threads_read_replies',
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $appId, string $appSecret, string $redirectUri, string $code): Response
    {
        return Http::asForm()->timeout(30)->post($this->oauthUrl('oauth/access_token'), [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
    }

    public function exchangeLongLivedToken(string $appSecret, string $token): Response
    {
        return Http::timeout(30)->get($this->oauthUrl('access_token'), [
            'grant_type' => 'th_exchange_token',
            'client_secret' => $appSecret,
            'access_token' => $token,
        ]);
    }

    public function me(string $token): Response
    {
        return Http::withToken($token)->timeout(30)->get($this->graphUrl('me'), [
            'fields' => 'id,username,name',
        ]);
    }

    /**
     * OAuth endpoints live on the unversioned host.
     */
    protected function oauthUrl(string $path): string
    {
        $base = preg_replace('#/v\d+\.\d+/?$#', '', rtrim((string) config('threads.graph_url', 'https://graph.threads.net/v1.0'), '/'));

        return $base.'/'.ltrim($path, '/');
    }

    protected function graphUrl(string $path): string
    {
        return rtrim(config('threads.graph_url', 'https://graph.threads.net/v1.0'), '/').'/'.ltrim($path, '/');
    }
}
