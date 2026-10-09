<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderEvent;
use App\Modules\Commerce\Services\OrderOwnerWhatsAppNotificationService;
use App\Modules\Commerce\Services\OrderWhatsAppNotificationService;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;

class NotifyOrderEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function __construct(public int $eventId)
    {
        $this->onQueue('commerce-orders');
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('order-event:'.$this->eventId))->expireAfter(120)];
    }

    public function handle(SystemNotificationService $notifications): void
    {
        $event = OrderEvent::query()->findOrFail($this->eventId);
        if ($event->notification_attempts >= 10 || ($event->staff_notified_at && $event->customer_notified_at && $event->whatsapp_notified_at && $event->owner_whatsapp_notified_at)) {
            return;
        }
        $event->increment('notification_attempts');
        $order = Order::query()->with('contact')->findOrFail($event->order_id);
        $owner = Workspace::query()->findOrFail($order->workspace_id)->owner;
        if (! $event->staff_notified_at && $owner) {
            $notifications->send($owner, ['title' => $order->number.' — '.$event->label, 'body' => $order->currency.' '.($order->total ?? $order->subtotal), 'url' => route('user.commerce.orders.show', $order)], 'commerce');
            $event->update(['staff_notified_at' => now()]);
        }
        $email = $order->customer_snapshot !== null ? ($order->customer_snapshot['email'] ?? null) : $order->contact?->email;
        if (! $event->customer_notified_at && $email) {
            Mail::raw($event->label."\nOrder: {$order->number}\nTracking code: {$order->tracking_code}\nCurrency: {$order->currency}\n".($order->shipping_quote_required ? 'Shipping quote required.' : "Total: {$order->total}")."\n".$order->payment_instructions, function ($message) use ($order, $event, $email): void {
                $message->to($email)->subject($order->number.' — '.$event->label);
            });
            $event->update(['customer_notified_at' => now()]);
        }
        if (! $email && ! $event->customer_notified_at) {
            $event->update(['customer_notified_at' => now()]);
        }
        if (! $event->whatsapp_notified_at) {
            app(OrderWhatsAppNotificationService::class)->send($order, $event);
            $event->update(['whatsapp_notified_at' => now()]);
        }
        if (! $event->owner_whatsapp_notified_at && app(OrderOwnerWhatsAppNotificationService::class)->send($order, $event)) {
            $event->update(['owner_whatsapp_notified_at' => now()]);
        }
    }
}
