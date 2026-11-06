<?php

namespace App\Modules\TikTok\Jobs;

use App\Modules\TikTok\Models\TikTokPost;
use App\Modules\TikTok\Services\TikTokPostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Uploads the video to TikTok (direct post or inbox draft).
 */
class PublishTikTokPostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $postId) {}

    public function handle(TikTokPostService $posts): void
    {
        $post = TikTokPost::query()->find($this->postId);

        if ($post && $post->status === TikTokPost::STATUS_QUEUED) {
            $posts->publish($post);
        }
    }
}
