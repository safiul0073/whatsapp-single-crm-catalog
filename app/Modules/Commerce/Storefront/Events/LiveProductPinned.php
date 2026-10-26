<?php

namespace App\Modules\Commerce\Storefront\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveProductPinned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $product
     */
    public function __construct(
        public int $streamId,
        public ?int $pinnedProductId,
        public ?array $product = null,
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
        return 'live.product.pinned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'pinned_product_id' => $this->pinnedProductId,
            'product' => $this->product,
        ];
    }
}
