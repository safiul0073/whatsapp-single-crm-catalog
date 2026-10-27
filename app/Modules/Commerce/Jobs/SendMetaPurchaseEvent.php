<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaPurchaseEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array{ip?: ?string, user_agent?: ?string, fbp?: ?string, fbc?: ?string, host?: ?string}  $client
     */
    public function __construct(public int $orderId, public array $client = []) {}

    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(MetaConversionsService $conversions): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $response = $conversions->sendPurchase($order, $this->client);

        if ($response && $response->serverError()) {
            $response->throw();
        }

        if ($response && $response->failed()) {
            logger()->warning('Meta Conversions API rejected a Purchase event.', ['order_id' => $order->id, 'error' => $response->json('error.message')]);
        }
    }
}
