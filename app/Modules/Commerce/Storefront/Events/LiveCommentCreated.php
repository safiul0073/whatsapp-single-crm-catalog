<?php

namespace App\Modules\Commerce\Storefront\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveCommentCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $comment
     */
    public function __construct(
        public int $streamId,
        public array $comment,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("live.{$this->streamId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'live.comment.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'comment' => $this->comment,
        ];
    }
}
