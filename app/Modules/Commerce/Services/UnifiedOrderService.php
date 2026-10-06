<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Shipping\Models\ShippingRate;
use App\Modules\Shipping\Services\ShippingCalculatorService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UnifiedOrderService
{
    public function __construct(protected OrderInventoryService $inventory, protected ShippingCalculatorService $shipping) {}

    public function preview(Workspace $workspace, array $data): array
    {
        $settings = StoreOrderSetting::forWorkspace($workspace->id);
        $precision = $settings->precision();
        $groups = [];
        foreach ($data['groups'] as $selection) {
            $product = Product::query()->where('workspace_id', $workspace->id)->with(['variants', 'tierPrices', 'colors'])->findOrFail($selection['product_id']);
            if ($product->status !== 'active' || $product->visibility !== 'published') {
                $this->invalid('groups', 'This product is unavailable.');
            }
            $mode = $selection['mode'];
            $group = ['product_id' => $product->id, 'product_name' => $product->name, 'mode' => $mode, 'color_id' => null, 'box_count' => 0, 'ratio' => [], 'multiplier' => 1, 'pieces_per_box' => 0, 'items' => []];
            if ($mode === 'retail') {
                if (! $product->isRetailEnabled()) {
                    $this->invalid('groups', 'This product is wholesale only.');
                }
                $variant = $product->variants->firstWhere('id', $selection['variant_id'] ?? null);
                if (! $variant || $variant->status !== 'active') {
                    $this->invalid('groups', 'Select an available product variant.');
                }
                $quantity = (int) $selection['quantity'];
                $price = OrderMoney::minor($product->single_piece_price ?? $variant->price, $precision);
                $group['items'][] = $this->item($variant, $quantity, $price, $precision);
            } else {
                if (! $product->isWholesaleEnabled()) {
                    $this->invalid('groups', 'Wholesale is unavailable for this product.');
                }
                if (isset($selection['ratio']) || isset($selection['pack_sizes'])) {
                    $this->invalid('groups', 'Box contents are configured by the store.');
                }
                $colorId = (int) $selection['color_id'];
                $ratio = $product->getEffectiveSizeRatios()[$colorId] ?? [];
                $ratio = array_filter($ratio, fn ($quantity) => (int) $quantity > 0);
                if (count($ratio) < max(1, (int) $product->ws_min_sizes)) {
                    $this->invalid('groups', 'The configured box does not meet the minimum sizes.');
                }
                $multiplier = max(1, (int) $product->ws_ratio_multiplier);
                $boxCount = (int) $selection['box_count'];
                $group = array_replace($group, ['color_id' => $colorId, 'color_name' => $product->colors->firstWhere('id', $colorId)?->name, 'box_count' => $boxCount, 'ratio' => $ratio, 'multiplier' => $multiplier, 'pieces_per_box' => array_sum($ratio) * $multiplier]);
                foreach ($ratio as $size => $perBox) {
                    $variant = $product->variants->first(fn ($variant) => $variant->color_id === $colorId && $variant->size === (string) $size && $variant->status === 'active');
                    if (! $variant) {
                        $this->invalid('groups', "The configured {$size} variant is unavailable.");
                    }
                    $group['items'][] = $this->item($variant, (int) $perBox * $multiplier * $boxCount, 0, $precision);
                }
            }
            $group['quantity'] = array_sum(array_column($group['items'], 'quantity'));
            $groups[] = $group;
        }
        foreach (collect($groups)->where('mode', 'wholesale')->groupBy('product_id') as $productId => $productGroups) {
            $product = Product::query()->with('tierPrices')->findOrFail($productId);
            $quantity = $productGroups->sum('quantity');
            if ($quantity < max(1, (int) $product->ws_main_moq)) {
                $this->invalid('groups', "{$product->name} requires at least {$product->ws_main_moq} pieces.");
            }
            foreach ($productGroups->groupBy('color_id') as $colorGroups) {
                if ($colorGroups->sum('quantity') < max(1, (int) $product->ws_color_moq)) {
                    $this->invalid('groups', 'The selected color does not meet its minimum quantity.');
                }
            }
            $tier = $product->tierPrices->sortByDesc('min_quantity')->first(fn ($tier) => $quantity >= $tier->min_quantity && ($tier->max_quantity === null || $quantity <= $tier->max_quantity));
            $price = $tier?->unit_price ?? $product->wholesale_price;
            if ($price === null || OrderMoney::minor($price, $precision) <= 0) {
                $this->invalid('price', 'Configure a wholesale price before ordering.');
            }
            foreach ($groups as &$group) {
                if ($group['mode'] === 'wholesale' && $group['product_id'] === $productId) {
                    foreach ($group['items'] as &$item) {
                        $item['unit_price'] = OrderMoney::decimal(OrderMoney::minor($price, $precision), $precision);
                    }
                    unset($item);
                }
            }
            unset($group);
        }
        $subtotal = 0;
        $items = [];
        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                $line = OrderMoney::minor($item['unit_price'], $precision) * $item['quantity'];
                $item['line_total'] = OrderMoney::decimal($line, $precision);
                $subtotal += $line;
                $items[] = ['product_id' => $group['product_id'], 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity']];
            }
            unset($item);
        }
        unset($group);
        $required = collect($groups)->flatMap(fn ($group) => $group['items'])->groupBy('variant_id')->map(fn ($entries) => $entries->sum('quantity'));
        $availability = [];
        foreach ($required as $variantId => $quantity) {
            $variant = ProductVariant::query()->where('workspace_id', $workspace->id)->findOrFail($variantId);
            $held = OrderReservation::query()->where('variant_id', $variantId)->where('state', 'reserved')->where('expires_at', '>', now())->sum('quantity');
            $availability[] = ['variant_id' => $variantId, 'sku' => $variant->sku, 'required' => $quantity, 'available' => max(0, $variant->stock_quantity - (int) $held)];
        }
        if (collect($groups)->sum('box_count') > 500) {
            $this->invalid('groups', 'An order can contain at most 500 boxes.');
        }
        $country = $data['shipping_address']['country'];
        $quote = $this->shipping->getQuote($workspace, $items, $country, $data['shipping_method_id'] ?? null);
        $rates = $quote['available_rates']->filter(fn ($rate) => $rate->currency === $settings->currency);
        $selected = isset($data['shipping_method_id']) ? $rates->firstWhere('shipping_method_id', $data['shipping_method_id']) : $rates->sortBy(fn ($rate) => $rate->price + $rate->price_per_kg * $rate->chargeable_weight_kg)->first();
        if (isset($data['shipping_method_id']) && ! $selected) {
            $this->invalid('shipping_method_id', 'This shipping method is unavailable for your destination and currency.');
        }
        $shipping = $selected ? $this->shippingAmount($selected, $precision) : 0;
        $discount = 0;
        foreach ($data['adjustments'] ?? [] as $adjustment) {
            if (($adjustment['currency'] ?? null) !== $settings->currency) {
                $this->invalid('adjustments', 'Discount currency does not match the store.');
            }
            $discount += OrderMoney::minor($adjustment['amount'], $precision);
        }
        if ($discount > $subtotal) {
            $this->invalid('adjustments', 'Discounts cannot exceed the merchandise subtotal.');
        }

        return ['weight' => $quote['weight_data'] ?? [], 'chargeable_weight_kg' => $selected?->chargeable_weight_kg, 'charges' => [], 'availability' => $availability, 'currency' => $settings->currency, 'precision' => $precision, 'shipping_method_id' => $selected?->shipping_method_id, 'delivery_method' => $selected?->method?->name, 'groups' => $groups, 'subtotal' => OrderMoney::decimal($subtotal, $precision), 'discount_amount' => OrderMoney::decimal($discount, $precision), 'shipping_amount' => $selected ? OrderMoney::decimal($shipping, $precision) : null, 'shipping_quote_required' => ! $selected, 'total' => $selected ? OrderMoney::decimal($subtotal + $shipping - $discount, $precision) : null, 'shipping_options' => $rates->map(fn ($rate) => ['id' => $rate->shipping_method_id, 'code' => $rate->method?->code, 'name' => $rate->method?->name ?? 'Shipping', 'currency' => $rate->currency, 'price' => OrderMoney::decimal($this->shippingAmount($rate, $precision), $precision)])->values()->all()];
    }

    public function create(Workspace $workspace, array $data): Order
    {
        return DB::transaction(function () use ($workspace, $data): Order {
            Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = Order::query()->where('workspace_id', $workspace->id)->where('submission_reference', $data['submission_reference'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'This submission reference has different contents.');

                return $existing;
            }
            $preview = $this->preview($workspace, $data);
            $settings = StoreOrderSetting::forWorkspace($workspace->id);
            $contact = isset($data['contact_id']) ? Contact::query()->where('workspace_id', $workspace->id)->findOrFail($data['contact_id']) : Contact::query()->firstOrCreate(['workspace_id' => $workspace->id, 'phone' => $data['customer']['phone']], ['name' => $data['customer']['name'], 'email' => $data['customer']['email'] ?? null, 'country' => $data['shipping_address']['country']]);
            $draft = $data['draft'] ?? false;
            $order = Order::query()->create([
                'workspace_id' => $workspace->id, 'contact_id' => $contact->id,
                'source' => $data['source'] ?? 'manual', 'submission_reference' => $data['submission_reference'], 'payload_hash' => $hash,
                'customer_reference' => $data['customer_reference'] ?? null, 'customer_snapshot' => $data['customer'] ?? $contact->only(['name', 'phone', 'email']), 'number' => 'ORD-'.Str::upper(Str::random(12)),
                'conversation_id' => $data['conversation_id'] ?? null, 'channel_account_id' => $data['channel_account_id'] ?? null, 'provider_message_id' => $data['provider_message_id'] ?? null, 'catalog_id' => $data['catalog_id'] ?? null, 'issues' => $data['issues'] ?? [], 'provider_payload' => array_replace($data['provider_payload'] ?? [], ['checkout_quote' => ['weight' => $preview['weight'], 'chargeable_weight_kg' => $preview['chargeable_weight_kg']]]),
                'tracking_code' => 'TRK-'.Str::upper(Str::random(24)), 'status' => $draft ? 'draft' : (! empty($data['issues']) ? 'needs_details' : ($preview['shipping_quote_required'] ? 'requested' : 'awaiting_payment')),
                'currency' => $preview['currency'], 'subtotal' => $preview['subtotal'], 'discount_amount' => $preview['discount_amount'], 'adjustments' => $data['adjustments'] ?? [],
                'shipping_method_id' => $preview['shipping_method_id'] ?? null, 'delivery_method' => $preview['delivery_method'] ?? null,
                'shipping_amount' => $preview['shipping_amount'], 'shipping_quote_required' => $preview['shipping_quote_required'], 'total' => $preview['total'],
                'shipping_address' => $data['shipping_address'], 'payment_instructions' => $settings->payment_instructions,
            ]);
            $this->saveGroups($order, $preview['groups'], $workspace->id);
            if (! $draft) {
                $this->inventory->reserve($order->load('items'));
            }
            $this->event($order, $draft ? 'draft' : 'placed', $draft ? 'Draft saved' : 'Order placed');

            return $order->load(['items', 'groups', 'boxes.contents.item', 'events', 'shipment']);
        }, 3);
    }

    public function completeRequest(Order $order, Workspace $workspace, array $data): Order
    {
        return DB::transaction(function () use ($order, $workspace, $data): Order {
            $locked = Order::query()->where('workspace_id', $workspace->id)->lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->status === 'needs_details' && $locked->source === 'native_whatsapp' && ! $locked->groups()->exists(), 422, 'This request has already been completed.');
            $preview = $this->preview($workspace, $data);
            $locked->items()->delete();
            $locked->update(array_merge(collect($preview)->only(['currency', 'subtotal', 'discount_amount', 'shipping_amount', 'shipping_quote_required', 'total', 'shipping_method_id', 'delivery_method'])->all(), [
                'shipping_address' => $data['shipping_address'], 'issues' => [], 'status' => $preview['shipping_quote_required'] ? 'requested' : 'awaiting_payment',
                'submission_reference' => $data['submission_reference'], 'payload_hash' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)), 'payment_instructions' => StoreOrderSetting::forWorkspace($workspace->id)->payment_instructions,
            ]));
            $this->saveGroups($locked, $preview['groups'], $workspace->id);
            $this->inventory->reserve($locked->load('items'));
            $this->event($locked, 'details_confirmed', 'Order details confirmed');

            return $locked;
        });
    }

    private function saveGroups(Order $order, array $groups, int $workspaceId): void
    {
        foreach ($groups as $snapshot) {
            $group = $order->groups()->create(collect($snapshot)->except('items')->all());
            $saved = [];
            foreach ($snapshot['items'] as $item) {
                $saved[] = $order->items()->create(array_merge($item, ['workspace_id' => $workspaceId, 'group_id' => $group->id, 'product_name' => $snapshot['product_name']]));
            }
            for ($index = 0; $index < $group->box_count; $index++) {
                $box = $order->boxes()->create(['group_id' => $group->id, 'label' => $order->number.'-BOX-'.($order->boxes()->count() + 1), 'kind' => 'wholesale']);
                foreach ($saved as $item) {
                    $box->contents()->create(['order_item_id' => $item->id, 'quantity' => intdiv($item->quantity, $group->box_count)]);
                }
            }
        }
    }

    public function event(Order $order, string $key, string $label): void
    {
        $event = $order->events()->firstOrCreate(['key' => $key], ['label' => $label, 'occurred_at' => now()]);
        if ($event->wasRecentlyCreated && $key !== 'draft') {
            DB::afterCommit(fn () => rescue(fn () => NotifyOrderEvent::dispatch($event->id), report: true));
        }
    }

    public function payload(Order $order): array
    {
        $order->loadMissing(['items', 'groups', 'boxes.contents.item', 'events', 'shipment']);

        $payload = array_merge($order->only(['id', 'number', 'source', 'submission_reference', 'status', 'currency', 'subtotal', 'discount_amount', 'adjustments', 'shipping_amount', 'shipping_quote_required', 'total', 'payment_state', 'payment_instructions', 'tracking_code', 'tracking_number', 'tracking_url', 'shipping_address']), ['items' => $order->items->toArray(), 'groups' => $order->groups->toArray(), 'boxes' => $order->boxes->toArray(), 'shipment' => $order->shipment?->toArray(), 'timeline' => $order->trackingTimeline()]);
        $precision = (new StoreOrderSetting(['currency' => $order->currency]))->precision();
        foreach (['subtotal', 'discount_amount', 'shipping_amount', 'total'] as $field) {
            if ($payload[$field] !== null) {
                $payload[$field] = OrderMoney::decimal(OrderMoney::minor($payload[$field], $precision), $precision);
            }
        }
        $payload['items'] = array_map(function (array $item) use ($precision): array {
            foreach (['unit_price', 'line_total'] as $field) {
                $item[$field] = OrderMoney::decimal(OrderMoney::minor($item[$field], $precision), $precision);
            }

            return $item;
        }, $payload['items']);
        $payload['precision'] = $precision;
        $payload['chargeable_weight_kg'] = $order->provider_payload['checkout_quote']['chargeable_weight_kg'] ?? null;
        $payload['charges'] = [];

        return $payload;

    }

    private function shippingAmount(ShippingRate $rate, int $precision): int
    {
        $weight = (int) ceil($rate->chargeable_weight_kg * 1000);

        return OrderMoney::minor($rate->price, $precision) + intdiv(OrderMoney::minor($rate->price_per_kg, $precision) * $weight + 500, 1000);
    }

    private function item(ProductVariant $variant, int $quantity, int $price, int $precision): array
    {
        return ['variant_id' => $variant->id, 'retailer_id' => $variant->meta_retailer_id ?? $variant->sku, 'sku' => $variant->sku, 'attributes' => array_merge($variant->attributes ?? [], ['size' => $variant->size]), 'quantity' => $quantity, 'unit_price' => OrderMoney::decimal($price, $precision)];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
