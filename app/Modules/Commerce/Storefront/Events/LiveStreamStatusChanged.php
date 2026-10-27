<?php

namespace App\Modules\Commerce\Storefront\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveStreamStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $stream
     */
    public function __construct(
        public int $workspaceId,
        public int $streamId,
        public string $status,
        public array $stream = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("store.{$this->workspaceId}.live"),
            new Channel("live.{$this->streamId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'live.status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'stream_id' => $this->streamId,
            'status' => $this->status,
            'stream' => $this->stream,
        ];
    }
}
