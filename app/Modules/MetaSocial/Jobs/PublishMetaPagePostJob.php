<?php

namespace App\Modules\MetaSocial\Jobs;

use App\Modules\MetaSocial\Models\MetaPagePost;
use App\Modules\MetaSocial\Services\MetaPagePostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishMetaPagePostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $postId) {}

    public function handle(MetaPagePostService $posts): void
    {
        $post = MetaPagePost::query()->find($this->postId);

        if ($post && $post->status === MetaPagePost::STATUS_SCHEDULED) {
            $posts->publish($post);
        }
    }
}
