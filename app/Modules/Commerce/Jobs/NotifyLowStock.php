<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderOwnerWhatsAppNotificationService;
use App\Modules\NotificationTemplates\Notifications\MobileAlert;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyLowStock implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public int $productId)
    {
        $this->onQueue('commerce-orders');
    }

    public function uniqueId(): string
    {
        return (string) $this->productId;
    }

    public function backoff(): array
    {
        return [60, 600];
    }

    /**
     * Tells the store owner which sizes and colors of a product are running low, in the dashboard bell,
     * as a mobile push and on WhatsApp.
     */
    public function handle(SystemNotificationService $notifications, OrderOwnerWhatsAppNotificationService $whatsapp): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product) {
            return;
        }

        $settings = StoreOrderSetting::forWorkspace($product->workspace_id);
        $threshold = max(0, (int) $settings->low_stock_threshold);
        $variants = ProductVariant::query()->with('color')->where('product_id', $product->id)->where('status', 'active')
            ->whereNotNull('low_stock_notified_at')->where('stock_quantity', '<=', $threshold)->orderBy('stock_quantity')->get();

        if (! $settings->low_stock_alerts_enabled || $variants->isEmpty()) {
            return;
        }

        $lines = $variants->map(fn (ProductVariant $variant): string => $this->label($variant).': '.max(0, (int) $variant->stock_quantity))->implode(', ');
        $title = 'Low stock: '.$product->name;
        $body = $lines." (alert at {$threshold} or less)";
        $url = route('user.commerce.inventory.index', ['q' => $product->name]);

        $owner = Workspace::query()->find($product->workspace_id)?->owner;
        if ($owner) {
            $notifications->send($owner, ['title' => $title, 'body' => $body, 'url' => $url], 'commerce');
            try {
                $owner->notify(new MobileAlert($title, $body, ['type' => 'low_stock', 'product_id' => (string) $product->id], $product->workspace_id));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $whatsapp->sendToOwner($product->workspace_id, "{$title}\n{$body}\n{$url}", ['product' => $product->id]);
    }

    protected function label(ProductVariant $variant): string
    {
        $color = $variant->attributes['color'] ?? $variant->color?->name;

        return collect([$color, $variant->size])->filter()->implode(' / ') ?: (string) $variant->sku;
    }
}
