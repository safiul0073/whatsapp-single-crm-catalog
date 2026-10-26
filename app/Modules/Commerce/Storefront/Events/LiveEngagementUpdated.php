<?php

namespace App\Modules\Commerce\Storefront\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveEngagementUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $streamId,
        public int $viewerCount,
        public int $facebookViewerCount,
        public int $likesCount,
        public int $totalViews,
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
        return 'live.engagement.updated';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'viewer_count' => $this->viewerCount,
            'facebook_viewer_count' => $this->facebookViewerCount,
            'likes_count' => $this->likesCount,
            'total_views' => $this->totalViews,
        ];
    }
}
