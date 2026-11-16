<?php

namespace App\Modules\Shipping\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Shipping\Services\ShipmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Opens the internal shipping record when an order is placed. It only creates a
 * pending record: nothing is quoted, booked or paid without a person approving it.
 */
class CreateShipmentForOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $orderId)
    {
        $this->onQueue('commerce-orders');
    }

    public function handle(ShipmentService $shipments): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order && ! in_array($order->status, ['draft', 'cancelled'], true)) {
            $shipments->createForOrder($order);
        }
    }
}
