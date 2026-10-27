<?php

namespace App\Modules\Commerce\Storefront\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveWebRtcSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $streamId,
        public string $type,
        public string $senderPeerId,
        public ?string $targetPeerId,
        public array $payload = [],
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
        return 'live.webrtc.signal';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'type' => $this->type,
            'sender_peer_id' => $this->senderPeerId,
            'target_peer_id' => $this->targetPeerId,
            'payload' => $this->payload,
        ];
    }
}
