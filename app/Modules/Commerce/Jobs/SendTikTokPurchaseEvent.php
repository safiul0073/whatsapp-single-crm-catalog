<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\MetaConversionsService;
use App\Modules\Commerce\Services\TikTokEventsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;

class SendTikTokPurchaseEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public int $tries = 3;

    /**
     * @param  array{ip?: ?string, user_agent?: ?string, ttp?: ?string, host?: ?string}  $client
     */
    public function __construct(public int $orderId, public array $client = []) {}

    public function uniqueId(): string
    {
        return 'tiktok-purchase:'.$this->orderId;
    }

    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(TikTokEventsService $events, MetaConversionsService $conversions): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order || $order->payment_state !== 'paid' || $order->status === 'cancelled') {
            return;
        }

        $client = $this->client ?: Arr::only(MetaConversionsService::rememberedClient($order), ['ip', 'user_agent', 'ttp', 'host', 'consent']);

        if (! $conversions->consented((int) $order->workspace_id, $client)) {
            return;
        }

        $response = $events->sendPurchase($order, $client);

        if ($response && $response->serverError()) {
            $response->throw();
        }

        $code = $response?->json('code');

        if ($response && ($response->failed() || ($code !== null && (int) $code !== 0))) {
            logger()->warning('TikTok Events API rejected a purchase event.', ['order_id' => $order->id, 'error' => $response->json('message')]);
        }
    }
}
