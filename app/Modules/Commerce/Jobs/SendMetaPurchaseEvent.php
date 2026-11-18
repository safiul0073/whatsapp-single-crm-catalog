<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Reports a storefront order as Purchase once its payment is confirmed. A cache marker set only after
 * Meta accepts the event stops repeat deliveries; retries before that still go through.
 */
class SendMetaPurchaseEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /**
     * @param  array<string, mixed>  $client  Legacy jobs queued at checkout carried the context inline.
     */
    public function __construct(public int $orderId, public array $client = []) {}

    public function uniqueId(): string
    {
        return 'meta-purchase:'.$this->orderId;
    }

    public function backoff(): array
    {
        return [30, 300];
    }

    public static function sentKey(Order $order): string
    {
        return 'meta-capi-sent:'.$order->workspace_id.':'.MetaConversionsService::eventId($order);
    }

    public function handle(MetaConversionsService $conversions): void
    {
        $order = Order::query()->with('items')->find($this->orderId);

        if (! $order || $order->payment_state !== 'paid' || $order->status === 'cancelled' || Cache::has(self::sentKey($order))) {
            return;
        }

        $client = $this->client ?: MetaConversionsService::rememberedClient($order);

        if (! $conversions->consented((int) $order->workspace_id, $client)) {
            return;
        }

        match ($conversions->sendPurchase($order, $client)) {
            MetaConversionsService::SENT => Cache::put(self::sentKey($order), true, now()->addDays(8)),
            MetaConversionsService::RATE_LIMITED => $this->release(120),
            MetaConversionsService::RETRY => throw new RuntimeException('Meta Conversions API is unavailable; retrying.'),
            default => null,
        };
    }
}
