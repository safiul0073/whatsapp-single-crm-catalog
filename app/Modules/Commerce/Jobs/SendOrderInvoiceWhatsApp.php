<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderInvoiceWhatsAppService;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderInvoiceWhatsApp implements ShouldBeUnique, ShouldQueue
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
        return 'order-invoice:'.$this->orderId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(OrderInvoiceWhatsAppService $invoices, SystemNotificationService $notifications): void
    {
        $order = Order::query()->with('contact')->findOrFail($this->orderId);
        $result = $invoices->send($order);

        $needsOwnerAttention = in_array($result['status'], [
            OrderInvoiceWhatsAppService::SKIPPED_WINDOW,
            OrderInvoiceWhatsAppService::SKIPPED_NO_CONVERSATION,
        ], true);
        $owner = $needsOwnerAttention ? Workspace::query()->find($order->workspace_id)?->owner : null;
        if ($owner) {
            $notifications->send($owner, [
                'title' => 'Invoice not sent: '.$order->number,
                'body' => $result['message'],
                'url' => route('user.commerce.orders.show', $order),
            ], 'commerce');
        }
    }
}
