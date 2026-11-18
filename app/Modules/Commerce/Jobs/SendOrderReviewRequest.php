<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderReviewRequestService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderReviewRequest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $uniqueFor = 600;

    public function __construct(public int $orderId)
    {
        $this->onQueue('commerce-orders');
    }

    public function uniqueId(): string
    {
        return 'order-review-request:'.$this->orderId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(OrderReviewRequestService $reviews): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order) {
            $reviews->send($order);
        }
    }
}
