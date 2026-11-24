<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderPackingSlipShareService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Shares one order's packing slip with one recipient; used by the bulk share on the orders list.
 * Not retried: every share is a new manual send, so a retry could deliver twice.
 */
class SendPackingSlipWhatsApp implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 120;

    /**
     * @param  array{name: ?string, phone: string}  $recipient
     */
    public function __construct(public int $orderId, public array $recipient)
    {
        $this->onQueue('commerce-orders');
    }

    public function uniqueId(): string
    {
        return 'packing-slip:'.$this->orderId.':'.$this->recipient['phone'];
    }

    public function handle(OrderPackingSlipShareService $share): void
    {
        $order = Order::query()->find($this->orderId);
        if (! $order) {
            return;
        }
        $result = $share->share($order, [$this->recipient]);
        if ($result['problems'] !== []) {
            Log::warning('Packing slip not shared on WhatsApp.', ['order' => $order->id, 'problems' => $result['problems']]);
        }
    }
}
