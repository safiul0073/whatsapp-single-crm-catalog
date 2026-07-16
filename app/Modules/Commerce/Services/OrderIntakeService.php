<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderIntakeService
{
    public function intake(ChannelAccount $account, Contact $contact, Conversation $conversation, array $message): Order
    {
        return DB::transaction(function () use ($account, $contact, $conversation, $message): Order {
            ChannelAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $providerMessageId = (string) ($message['id'] ?? 'order-'.Str::uuid());
            $existing = Order::query()->where('channel_account_id', $account->id)->where('provider_message_id', $providerMessageId)->first();
            if ($existing) {
                return $existing;
            }

            $providerCatalogId = (string) data_get($message, 'order.catalog_id', '');
            $catalog = Catalog::query()->where('workspace_id', $account->workspace_id)->where('channel_account_id', $account->id)->first();
            $issues = [];
            $subtotal = 0.0;
            $items = [];

            foreach (data_get($message, 'order.product_items', []) as $providerItem) {
                $retailerId = (string) ($providerItem['product_retailer_id'] ?? '');
                $quantity = max(1, (int) ($providerItem['quantity'] ?? 1));
                $variant = ProductVariant::query()->with('product')->where('workspace_id', $account->workspace_id)->where('meta_retailer_id', $retailerId)->first();
                $providerCurrency = strtoupper((string) ($providerItem['currency'] ?? 'USD'));
                $providerPrice = (float) ($providerItem['item_price'] ?? 0);
                if (! $variant) {
                    $issues[] = "Unknown retailer ID: {$retailerId}";
                } elseif ($variant->status !== 'active') {
                    $issues[] = "Inactive variant: {$retailerId}";
                }
                if ($providerCurrency !== 'USD') {
                    $issues[] = "Unsupported currency for {$retailerId}: {$providerCurrency}";
                }
                $unitPrice = $variant ? (float) $variant->price : $providerPrice;
                if ($variant && abs($unitPrice - $providerPrice) > 0.009) {
                    $issues[] = "Price mismatch for {$retailerId}";
                }
                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;
                $items[] = compact('variant', 'retailerId', 'quantity', 'providerPrice', 'unitPrice', 'lineTotal');
            }

            $order = Order::query()->create([
                'workspace_id' => $account->workspace_id,
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'channel_account_id' => $account->id,
                'catalog_id' => $catalog?->id,
                'number' => 'WA-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'provider_message_id' => $providerMessageId,
                'provider_catalog_id' => $providerCatalogId,
                'status' => $issues === [] ? 'requested' : 'needs_details',
                'currency' => 'USD',
                'subtotal' => $subtotal,
                'issues' => $issues,
                'provider_payload' => $message,
            ]);

            foreach ($items as $item) {
                $variant = $item['variant'];
                $order->items()->create([
                    'workspace_id' => $account->workspace_id,
                    'variant_id' => $variant?->id,
                    'retailer_id' => $item['retailerId'],
                    'sku' => $variant?->sku,
                    'product_name' => $variant?->product?->name ?? 'Unknown product',
                    'attributes' => $variant?->attributes ?? [],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unitPrice'],
                    'line_total' => $item['lineTotal'],
                    'provider_unit_price' => $item['providerPrice'],
                ]);
            }

            return $order->load('items');
        });
    }
}
