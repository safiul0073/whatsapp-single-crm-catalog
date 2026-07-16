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

    protected function graphUrl(string $path): string
    {
        return rtrim(config('threads.graph_url', 'https://graph.threads.net/v1.0'), '/').'/'.ltrim($path, '/');
    }
}
