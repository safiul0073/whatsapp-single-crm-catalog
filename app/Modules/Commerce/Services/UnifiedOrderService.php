<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Jobs\NotifyOrderEvent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Storefront\Services\PackSelectionService;
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
                if (array_key_exists('size_quantities', $selection) && ($data['source'] ?? null) !== 'pos' && ! isset($selection['pack_snapshot'])) {
                    $this->invalid('groups', 'Custom pack contents are only available in the POS.');
                }
                $colorId = (int) $selection['color_id'];
                $color = $product->colors->firstWhere('id', $colorId);
                if (! $color) {
                    $this->invalid('groups', 'Select a color available for this product.');
                }
                $customSizeQuantities = $selection['size_quantities'] ?? null;
                $ratio = is_array($customSizeQuantities)
                    ? $customSizeQuantities
                    : ($product->getEffectiveSizeRatios()[$colorId] ?? []);
                $ratio = array_filter($ratio, fn ($quantity) => (int) $quantity > 0);
                if ($ratio === []) {
                    $this->invalid('groups', 'This pack has no sizes configured.');
                }
                $editablePackContents = is_array($customSizeQuantities);
                $multiplier = $editablePackContents ? 1 : max(1, (int) $product->ws_ratio_multiplier);
                if (isset($selection['pack_snapshot'])) {
                    $group['pack_snapshot'] = $selection['pack_snapshot'];
                }
                if ($editablePackContents && ! isset($selection['pack_snapshot'])) {
                    $minimumSizes = max(1, (int) ($product->ws_min_sizes ?? 1));
                    $colorMoq = max(1, (int) ($product->ws_color_moq ?? 1));
                    if (count($ratio) < $minimumSizes) {
                        $this->invalid('groups', "{$color->display_name} must include at least {$minimumSizes} sizes per pack.");
                    }
                    foreach ($ratio as $size => $quantity) {
                        if (! $product->variants->contains(fn ($variant): bool => $variant->color_id === $colorId && $variant->size === (string) $size && $variant->status === 'active')) {
                            $this->invalid('groups', "The {$size} size is unavailable for {$color->display_name}.");
                        }
                        if ((int) $quantity < $colorMoq) {
                            $this->invalid('groups', "Each selected {$color->display_name} size requires at least {$colorMoq} pieces per pack.");
                        }
                    }
                }
                $boxCount = (int) $selection['box_count'];
                $group = array_replace($group, ['color_id' => $colorId, 'color_name' => $color->display_name, 'box_count' => $boxCount, 'ratio' => $ratio, 'multiplier' => $multiplier, 'pieces_per_box' => array_sum($ratio) * $multiplier]);
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
            $standardGroups = $productGroups->filter(fn ($g) => empty($g['pack_snapshot']));
            $packPricing = $product->usesPackPricing() && $standardGroups->isNotEmpty();
            if ($packPricing && $standardGroups->sum('box_count') < $product->minimumPacks()) {
                $this->invalid('groups', "{$product->name} requires at least {$product->minimumPacks()} packs.");
            }
            if (! $packPricing && $standardGroups->isNotEmpty() && $standardGroups->sum('quantity') < max(1, (int) $product->ws_main_moq)) {
                $this->invalid('groups', "{$product->name} requires at least {$product->ws_main_moq} pieces.");
            }
            foreach ($standardGroups->groupBy('color_id') as $colorGroups) {
                if ($colorGroups->sum('quantity') < max(1, (int) $product->ws_color_moq)) {
                    $this->invalid('groups', 'The selected color does not meet its minimum quantity.');
                }
            }
            $tier = $product->tierPrices->sortByDesc('min_quantity')->first(fn ($tier) => $quantity >= $tier->min_quantity && ($tier->max_quantity === null || $quantity <= $tier->max_quantity));
            $price = $tier?->unit_price ?? $product->wholesale_price;
            if ($price === null || OrderMoney::minor($price, $precision) <= 0) {
                $this->invalid('price', 'Configure a wholesale price before ordering.');
            }
            $packShares = [];
            if ($packPricing) {
                // Price list total for all standard packs, split across their lines so the sum is exact.
                $weights = [];
                foreach ($groups as $groupIndex => $group) {
                    if ($group['mode'] === 'wholesale' && $group['product_id'] === $productId && empty($group['pack_snapshot'])) {
                        foreach ($group['items'] as $itemIndex => $item) {
                            $weights[$groupIndex.':'.$itemIndex] = $item['quantity'];
                        }
                    }
                }
                $packShares = OrderMoney::allocate((int) $product->packTotalMinor($standardGroups->sum('box_count'), $precision), $weights);
            }
            foreach ($groups as $groupIndex => &$group) {
                if ($group['mode'] === 'wholesale' && $group['product_id'] === $productId) {
                    foreach ($group['items'] as $itemIndex => &$item) {
                        $share = $packShares[$groupIndex.':'.$itemIndex] ?? null;
                        if ($share !== null) {
                            $item['line_minor'] = $share;
                            $item['unit_price'] = OrderMoney::decimal(intdiv($share, max(1, $item['quantity'])), $precision);
                        } else {
                            $item['unit_price'] = OrderMoney::decimal(OrderMoney::minor($price, $precision), $precision);
                        }
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
                $line = $item['line_minor'] ?? OrderMoney::minor($item['unit_price'], $precision) * $item['quantity'];
                unset($item['line_minor']);
                $item['line_total'] = OrderMoney::decimal($line, $precision);
                $subtotal += $line;
                // Wholesale prices include shipping, so only retail lines are weighed for the shipping quote.
                if ($group['mode'] === 'retail') {
                    $items[] = ['product_id' => $group['product_id'], 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity']];
                }
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
        if (($data['source'] ?? null) === 'pos' && collect($availability)->contains(fn (array $stock): bool => $stock['required'] > $stock['available'])) {
            $this->invalid('groups', 'One or more selected sizes do not have enough available stock.');
        }
        if (app(PackSelectionService::class)->physicalBoxCount($groups) > 500) {
            $this->invalid('groups', 'An order can contain at most 500 boxes.');
        }
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

        if ((($data['source'] ?? null) === 'pos' && ($data['fulfillment_type'] ?? 'delivery') === 'pickup') || $items === []) {
            return ['shipping_required' => false, 'weight' => [], 'chargeable_weight_kg' => null, 'charges' => [], 'availability' => $availability, 'currency' => $settings->currency, 'precision' => $precision, 'shipping_method_id' => null, 'delivery_method' => 'Pickup', 'groups' => $groups, 'subtotal' => OrderMoney::decimal($subtotal, $precision), 'discount_amount' => OrderMoney::decimal($discount, $precision), 'shipping_amount' => OrderMoney::decimal(0, $precision), 'shipping_quote_required' => false, 'total' => OrderMoney::decimal($subtotal - $discount, $precision), 'shipping_options' => []];
        }
        $country = $data['shipping_address']['country'];
        $quote = $this->shipping->getQuote($workspace, $items, $country, $data['shipping_method_id'] ?? null);
        $rates = $quote['available_rates']->filter(fn ($rate) => $rate->currency === $settings->currency);
        $selected = isset($data['shipping_method_id']) ? $rates->firstWhere('shipping_method_id', $data['shipping_method_id']) : $rates->sortBy(fn ($rate) => $rate->price + $rate->price_per_kg * $rate->chargeable_weight_kg)->first();
        if (isset($data['shipping_method_id']) && ! $selected) {
            $this->invalid('shipping_method_id', 'This shipping method is unavailable for your destination and currency.');
        }
        $shipping = $selected ? $this->shippingAmount($selected, $precision) : 0;

        return ['shipping_required' => true, 'weight' => $quote['weight_data'] ?? [], 'chargeable_weight_kg' => $selected?->chargeable_weight_kg, 'charges' => [], 'availability' => $availability, 'currency' => $settings->currency, 'precision' => $precision, 'shipping_method_id' => $selected?->shipping_method_id, 'delivery_method' => $selected?->method?->name, 'groups' => $groups, 'subtotal' => OrderMoney::decimal($subtotal, $precision), 'discount_amount' => OrderMoney::decimal($discount, $precision), 'shipping_amount' => $selected ? OrderMoney::decimal($shipping, $precision) : null, 'shipping_quote_required' => ! $selected, 'total' => $selected ? OrderMoney::decimal($subtotal + $shipping - $discount, $precision) : null, 'shipping_options' => $rates->map(fn ($rate) => ['id' => $rate->shipping_method_id, 'code' => $rate->method?->code, 'name' => $rate->method?->name ?? 'Shipping', 'currency' => $rate->currency, 'price' => OrderMoney::decimal($this->shippingAmount($rate, $precision), $precision)])->values()->all()];
    }

    public function create(Workspace $workspace, array $data): Order
    {
        return DB::transaction(function () use ($workspace, $data): Order {
            Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = Order::query()->where('workspace_id', $workspace->id)->where('submission_reference', $data['submission_reference'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'This submission reference has different contents.');

                return $existing;
            }
            $preview = $this->preview($workspace, $data);
            $settings = StoreOrderSetting::forWorkspace($workspace->id);
            $walkIn = ($data['source'] ?? null) === 'pos' && ($data['walk_in'] ?? false);
            $contact = $walkIn ? null : (isset($data['contact_id']) ? Contact::query()->where('workspace_id', $workspace->id)->findOrFail($data['contact_id']) : $this->orderContact($workspace, $data));
            $draft = $data['draft'] ?? false;
            $orderNumber = ! empty($data['number']) ? $data['number'] : $this->generateOrderNumber($workspace);
            $order = Order::query()->create([
                'workspace_id' => $workspace->id, 'contact_id' => $contact?->id,
                'source' => $data['source'] ?? 'manual', 'submission_reference' => $data['submission_reference'], 'payload_hash' => $hash,
                'customer_reference' => $data['customer_reference'] ?? null, 'customer_snapshot' => $walkIn ? ['name' => 'Walk-in customer'] : (isset($data['contact_id']) ? $contact->only(['name', 'phone', 'email']) : $data['customer']), 'number' => $orderNumber,
                'conversation_id' => $data['conversation_id'] ?? null, 'channel_account_id' => $data['channel_account_id'] ?? null, 'provider_message_id' => $data['provider_message_id'] ?? null, 'catalog_id' => $data['catalog_id'] ?? null, 'issues' => $data['issues'] ?? [], 'provider_payload' => array_replace($data['provider_payload'] ?? [], ['checkout_quote' => ['weight' => $preview['weight'], 'chargeable_weight_kg' => $preview['chargeable_weight_kg']]]),
                'tracking_code' => 'TRK-'.Str::upper(Str::random(24)), 'status' => $draft ? 'draft' : (! empty($data['issues']) ? 'needs_details' : ($preview['shipping_quote_required'] ? 'requested' : 'awaiting_payment')),
                'currency' => $preview['currency'], 'subtotal' => $preview['subtotal'], 'discount_amount' => $preview['discount_amount'], 'adjustments' => $data['adjustments'] ?? [],
                'shipping_method_id' => $preview['shipping_method_id'] ?? null, 'delivery_method' => $preview['delivery_method'] ?? null,
                'shipping_amount' => $preview['shipping_amount'], 'shipping_quote_required' => $preview['shipping_quote_required'], 'total' => $preview['total'],
                'fulfillment_type' => ($data['source'] ?? null) === 'pos' ? ($data['fulfillment_type'] ?? 'delivery') : 'delivery',
                'shipping_address' => $data['shipping_address'] ?? [], 'payment_instructions' => $settings->payment_instructions,
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
        $customBoxes = [];
        foreach ($groups as $snapshot) {
            $group = $order->groups()->create(collect($snapshot)->except('items')->all());
            $saved = [];
            foreach ($snapshot['items'] as $item) {
                $saved[] = $order->items()->create(array_merge($item, ['workspace_id' => $workspaceId, 'group_id' => $group->id, 'product_name' => $snapshot['product_name']]));
            }
            for ($index = 0; $index < $group->box_count; $index++) {
                $packKey = null;
                if (isset($snapshot['pack_snapshot'])) {
                    $instanceKey = $snapshot['pack_snapshot']['cart_group_id']
                        ?? $snapshot['pack_snapshot']['pack_instance_id']
                        ?? (($snapshot['pack_snapshot']['template_id'] ?? 'custom').':'.json_encode($snapshot['pack_snapshot']['selection'] ?? []));
                    $packKey = $instanceKey.':'.$index;
                }
                $box = $packKey && isset($customBoxes[$packKey]) ? $customBoxes[$packKey] : $order->boxes()->create(['group_id' => $group->id, 'label' => $order->number.'-BOX-'.($order->boxes()->count() + 1), 'kind' => 'wholesale']);
                if ($packKey) {
                    $customBoxes[$packKey] = $box;
                }
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
        $order->loadMissing([
            'items.variant.media',
            'items.variant.color.swatchMedia',
            'items.variant.product.primaryMedia',
            'items.variant.product.gallery.media',
            'groups',
            'boxes.contents.item',
            'events',
            'shipment',
        ]);

        $metaByItemId = $order->items->mapWithKeys(function (OrderItem $item): array {
            $variant = $item->variant;
            $product = $variant?->product;
            $color = $variant?->color;
            $colorImageUrl = $variant?->media?->url
                ?? $color?->swatchMedia?->url
                ?? ($product && $variant?->color_id ? $product->gallery->firstWhere('color_id', $variant->color_id)?->media?->url : null)
                ?? $product?->primaryMedia?->url
                ?? $product?->gallery->first()?->media?->url;
            $productImageUrl = $product?->primaryMedia?->url ?? $colorImageUrl;

            return [
                $item->id => [
                    'image_url' => $colorImageUrl,
                    'product_image_url' => $productImageUrl,
                    'product_slug' => $product?->slug,
                    'color_id' => $variant?->color_id,
                    'color_name' => $color?->display_name ?? $color?->name ?? ($item->attributes['color'] ?? null),
                    'color_hex' => $color?->hex_code,
                    'size' => $variant?->size ?? ($item->attributes['size'] ?? null),
                ],
            ];
        });

        $payload = array_merge(
            $order->only([
                'id',
                'number',
                'source',
                'submission_reference',
                'status',
                'currency',
                'subtotal',
                'discount_amount',
                'adjustments',
                'shipping_amount',
                'shipping_quote_required',
                'delivery_method',
                'total',
                'payment_state',
                'payment_instructions',
                'tracking_code',
                'tracking_number',
                'tracking_url',
                'shipping_address',
                'customer_snapshot',
            ]),
            [
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->toArray(),
                'groups' => $order->groups->toArray(),
                'boxes' => $order->boxes->toArray(),
                'shipment' => $order->shipment?->toArray(),
                'timeline' => $order->trackingTimeline(),
            ]
        );
        $precision = (new StoreOrderSetting(['currency' => $order->currency]))->precision();
        foreach (['subtotal', 'discount_amount', 'shipping_amount', 'total'] as $field) {
            if ($payload[$field] !== null) {
                $payload[$field] = OrderMoney::decimal(OrderMoney::minor($payload[$field], $precision), $precision);
            }
        }
        $payload['items'] = array_map(function (array $item) use ($precision, $metaByItemId): array {
            foreach (['unit_price', 'line_total'] as $field) {
                $item[$field] = OrderMoney::decimal(OrderMoney::minor($item[$field], $precision), $precision);
            }
            unset($item['variant']);
            $meta = $metaByItemId[$item['id']] ?? [];
            $item['image_url'] = $meta['image_url'] ?? null;
            $item['product_image_url'] = $meta['product_image_url'] ?? null;
            $item['product_slug'] = $meta['product_slug'] ?? null;
            $item['color_id'] = $meta['color_id'] ?? null;
            $item['color_name'] = $meta['color_name'] ?? ($item['attributes']['color'] ?? null);
            $item['color_hex'] = $meta['color_hex'] ?? null;
            $item['size'] = $meta['size'] ?? ($item['attributes']['size'] ?? null);

            return $item;
        }, $payload['items']);
        $itemsById = collect($payload['items'])->keyBy('id');
        $itemsByGroupId = collect($payload['items'])->groupBy('group_id');
        $payload['groups'] = array_map(function (array $group) use ($itemsByGroupId, $precision): array {
            $groupItems = $itemsByGroupId->get($group['id'], collect());
            $firstItem = $groupItems->first();
            $sizeBreakdown = [];
            foreach ($groupItems as $gi) {
                $sz = (string) ($gi['size'] ?? $gi['attributes']['size'] ?? 'STD');
                $sizeBreakdown[$sz] = ($sizeBreakdown[$sz] ?? 0) + (int) ($gi['quantity'] ?? 0);
            }
            $groupMinorTotal = $groupItems->sum(fn (array $gi): int => OrderMoney::minor($gi['line_total'], $precision));
            $group['product_slug'] = $firstItem['product_slug'] ?? null;
            $group['product_image_url'] = $firstItem['product_image_url'] ?? $firstItem['image_url'] ?? null;
            $group['color_image_url'] = $firstItem['image_url'] ?? null;
            $group['color_hex'] = $firstItem['color_hex'] ?? null;
            $group['color_name'] = $group['color_name'] ?? $firstItem['color_name'] ?? null;
            $group['unit_price'] = $firstItem['unit_price'] ?? OrderMoney::decimal(0, $precision);
            $group['line_total'] = OrderMoney::decimal($groupMinorTotal, $precision);
            $group['size_breakdown'] = $sizeBreakdown;

            return $group;
        }, $payload['groups']);
        $payload['boxes'] = array_map(function (array $box) use ($itemsById): array {
            $box['contents'] = array_map(fn (array $content): array => array_merge($content, ['item' => $itemsById[$content['order_item_id']] ?? $content['item']]), $box['contents']);

            return $box;
        }, $payload['boxes']);
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
        return ['variant_id' => $variant->id, 'retailer_id' => $variant->meta_retailer_id ?? $variant->sku, 'sku' => $variant->sku, 'attributes' => array_filter(array_merge($variant->attributes ?? [], ['size' => $variant->size, 'color' => $variant->color?->name ?? $variant->attributes['color'] ?? null]), fn ($value) => $value !== null), 'quantity' => $quantity, 'unit_price' => OrderMoney::decimal($price, $precision)];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /**
     * Storefront checkout is unauthenticated, so an order never attaches to another person's contact via
     * a typed email. When the email already belongs to a different contact (unique per workspace), the new
     * contact is stored without it; the order's customer snapshot still records the email.
     */
    protected function orderContact(Workspace $workspace, array $data): Contact
    {
        $customer = $data['customer'];
        $existing = Contact::query()->where('workspace_id', $workspace->id)->where('phone', $customer['phone'])->first();

        if ($existing) {
            return $existing;
        }

        $email = filled($customer['email'] ?? null) ? strtolower(trim((string) $customer['email'])) : null;
        $isEmailTaken = $email !== null && Contact::query()->where('workspace_id', $workspace->id)->where('email', $email)->exists();

        return Contact::query()->create([
            'workspace_id' => $workspace->id,
            'phone' => $customer['phone'],
            'name' => $customer['name'],
            'email' => $isEmailTaken ? null : $email,
            'country' => $data['shipping_address']['country'] ?? null,
        ]);
    }

    public function generateOrderNumber(Workspace $workspace): string
    {
        $settings = StoreOrderSetting::query()
            ->where('workspace_id', $workspace->id)
            ->lockForUpdate()
            ->first() ?? StoreOrderSetting::forWorkspace($workspace->id);

        $prefix = (string) ($settings->order_number_prefix ?? 'ORD-');
        $digits = max(3, min(10, (int) ($settings->order_number_digits ?? 5)));

        $highest = $this->resolveHighestOrderSequence($workspace->id, $prefix);
        $seq = (int) ($settings->next_order_sequence ?? 0);
        if ($seq <= $highest) {
            $seq = $highest + 1;
        }
        if ($seq < 1) {
            $seq = 1;
        }

        do {
            $formatted = sprintf('%s%0'.$digits.'d', $prefix, $seq);
            $seq++;
            $exists = Order::query()
                ->where('workspace_id', $workspace->id)
                ->where('number', $formatted)
                ->exists();
        } while ($exists);

        $settings->next_order_sequence = $seq;
        $settings->save();

        return $formatted;
    }

    public function resolveHighestOrderSequence(int $workspaceId, string $prefix = 'ORD-'): int
    {
        $orders = Order::query()
            ->where('workspace_id', $workspaceId)
            ->pluck('number');

        $max = 0;
        $quotedPrefix = preg_quote($prefix, '/');
        foreach ($orders as $num) {
            if (preg_match('/^'.$quotedPrefix.'(\d+)$/', (string) $num, $matches)) {
                $val = (int) $matches[1];
                if ($val > $max) {
                    $max = $val;
                }
            }
        }

        return $max;
    }
}
