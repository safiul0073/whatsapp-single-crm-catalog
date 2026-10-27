<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\TikTokEventsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTikTokPurchaseEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array{ip?: ?string, user_agent?: ?string, ttp?: ?string, host?: ?string}  $client
     */
    public function __construct(public int $orderId, public array $client = []) {}

    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(TikTokEventsService $events): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $response = $events->sendPurchase($order, $this->client);

        if ($response && $response->serverError()) {
            $response->throw();
        }

        $code = $response?->json('code');

        if ($response && ($response->failed() || ($code !== null && (int) $code !== 0))) {
            logger()->warning('TikTok Events API rejected a purchase event.', ['order_id' => $order->id, 'error' => $response->json('message')]);
        }
    }
}
