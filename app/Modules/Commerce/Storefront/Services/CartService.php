<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartGroup;
use App\Modules\Commerce\Storefront\Models\CartItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function create(int $workspace, ?int $customer = null): array
    {
        $token = Str::random(64);
        $cart = Cart::query()->create(['workspace_id' => $workspace, 'customer_id' => $customer, 'token_hash' => hash('sha256', $token), 'currency' => StoreOrderSetting::catalogCurrency($workspace), 'expires_at' => now()->addDays(30)]);

        return [$cart, $token];
    }

    public function resolve(Request $r, bool $lock = false): Cart
    {
        $q = Cart::query()->where('workspace_id', $r->attributes->get('store_workspace')->id)->where('token_hash', hash('sha256', (string) $r->header('X-Cart-Token')))->where('expires_at', '>', now())->whereNull('completed_at');
        if ($lock) {
            $q->lockForUpdate();
        }
        $cart = $q->firstOrFail();
        abort_if($cart->customer_id && $cart->customer_id !== $r->attributes->get('store_customer')?->id, 404);

        return $cart;
    }

    /**
     * Adds retail units of a variant to the cart, merging into its existing retail line.
     * Call inside a transaction with the cart row locked.
     */
    public function addRetail(Cart $cart, int $variantId, int $quantity): CartItem
    {
        $v = ProductVariant::query()->where('workspace_id', $cart->workspace_id)->whereIn('status', ['active', 'out_of_stock'])->with('product')->lockForUpdate()->findOrFail($variantId);
        abort_unless($v->product->status === 'active' && $v->product->visibility === 'published' && $v->product->isRetailEnabled(), 422, 'Product unavailable.');
        $group = CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->get()->first(fn ($g) => $g->selection['type'] === 'retail' && (int) $g->selection['variant_id'] === $v->id);
        $group ??= CartGroup::query()->create(['workspace_id' => $cart->workspace_id, 'cart_id' => $cart->id, 'selection' => ['type' => 'retail', 'variant_id' => $v->id, 'quantity' => 0]]);
        $item = CartItem::query()->firstOrNew(['cart_id' => $cart->id, 'group_id' => $group->id, 'variant_id' => $v->id], ['workspace_id' => $cart->workspace_id, 'product_id' => $v->product_id, 'quantity' => 0]);
        abort_if(CartItem::query()->where('cart_id', $cart->id)->count() >= 50 && ! $item->exists, 422, 'Cart is full.');
        $item->quantity += $quantity;
        abort_if($item->quantity > 10000, 422);
        $available = $v->availableQuantity(ProductVariant::reservedQuantities([$v->id])[$v->id] ?? 0);
        if ($item->quantity > $available) {
            $this->outOfStock($available);
        }
        $item->price_snapshot = $v->product->single_piece_price ?? $v->price;
        $item->save();
        $group->update(['selection' => ['type' => 'retail', 'variant_id' => $v->id, 'quantity' => $item->quantity]]);

        return $item;
    }

    /**
     * Sets the quantity of a retail line. Only increases are checked against available stock.
     * Call inside a transaction with the cart row locked.
     */
    public function setRetailQuantity(Cart $cart, int $itemId, int $quantity): CartItem
    {
        $item = CartItem::query()->where('cart_id', $cart->id)->findOrFail($itemId);
        $group = $item->group_id ? CartGroup::query()->where('cart_id', $cart->id)->findOrFail($item->group_id) : null;
        abort_if($group && $group->selection['type'] !== 'retail', 422, 'Edit the complete pack instead.');
        $variant = ProductVariant::query()->where('workspace_id', $cart->workspace_id)->with('product')->findOrFail($item->variant_id);
        abort_unless($variant->product->isRetailEnabled(), 422, 'Remove this selection and rebuild a wholesale pack.');
        if ($quantity > $item->quantity) {
            $available = $variant->availableQuantity(ProductVariant::reservedQuantities([$variant->id])[$variant->id] ?? 0);
            if ($quantity > $available) {
                $this->outOfStock($available);
            }
        }
        $group ??= CartGroup::query()->create(['workspace_id' => $cart->workspace_id, 'cart_id' => $cart->id, 'selection' => []]);
        $item->update(['quantity' => $quantity, 'group_id' => $group->id]);
        $group->update(['selection' => ['type' => 'retail', 'variant_id' => $item->variant_id, 'quantity' => $item->quantity]]);

        return $item;
    }

    /**
     * Removes a retail line. Lines inside a wholesale pack are removed with the whole pack.
     */
    public function removeLine(Cart $cart, int $itemId): void
    {
        $item = CartItem::query()->where('cart_id', $cart->id)->findOrFail($itemId);
        $group = $item->group_id ? CartGroup::query()->where('cart_id', $cart->id)->findOrFail($item->group_id) : null;
        abort_if($group && $group->selection['type'] !== 'retail', 422, 'Remove the complete pack instead.');
        $item->delete();
        $group?->update(['removed_at' => now()]);
    }

    /**
     * Removes a cart group (a wholesale pack or a retail selection) with all its lines.
     */
    public function removeGroup(Cart $cart, int $groupId): void
    {
        $group = CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->findOrFail($groupId);
        CartItem::query()->where('cart_id', $cart->id)->where('group_id', $groupId)->delete();
        $group->update(['removed_at' => now()]);
    }

    public function clear(Cart $cart): void
    {
        CartItem::query()->where('cart_id', $cart->id)->delete();
        CartGroup::query()->where('cart_id', $cart->id)->update(['removed_at' => now()]);
        $cart->update(['coupon_code' => null]);
    }

    /**
     * @throws HttpResponseException
     */
    private function outOfStock(int $available): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $available > 0 ? "Only {$available} left in stock." : 'This item is out of stock.',
            'code' => 'out_of_stock',
            'available' => $available,
        ], 422));
    }

    /**
     * Rebuild wholesale pack lines whose saved selection now expands to different contents
     * (the owner changed the product's pack configuration after it was added).
     *
     * @return list<array{group_id: int, name: string, before: array<string, int>, after: array<string, int>}>
     */
    public function refreshStalePacks(Cart $cart): array
    {
        return DB::transaction(function () use ($cart): array {
            $notices = [];
            $packs = app(PackSelectionService::class);
            $groups = CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->lockForUpdate()->get();
            $lines = CartItem::query()->where('cart_id', $cart->id)->get();

            foreach ($groups as $group) {
                if (($group->selection['type'] ?? 'retail') === 'retail') {
                    continue;
                }
                try {
                    $expanded = $packs->validate($cart->workspace_id, $group->selection);
                } catch (ValidationException) {
                    continue;
                }
                $expected = collect($expanded['rows'])->groupBy('variant_id')->map(fn ($r) => $r->sum('quantity'))->sortKeys()->all();
                $groupLines = $lines->where('group_id', $group->id);
                $actual = $groupLines->groupBy('variant_id')->map(fn ($r) => $r->sum('quantity'))->sortKeys()->all();
                if ($expected === $actual) {
                    continue;
                }

                CartItem::query()->where('cart_id', $cart->id)->where('group_id', $group->id)->delete();
                foreach ($expanded['rows'] as $row) {
                    CartItem::query()->create(['workspace_id' => $cart->workspace_id, 'cart_id' => $cart->id, 'group_id' => $group->id, ...collect($row)->except('mode')->all()]);
                }
                $notices[] = [
                    'group_id' => $group->id,
                    'name' => (string) ($expanded['name'] ?? ''),
                    'before' => $this->sizeQuantities($cart->workspace_id, $actual),
                    'after' => $this->sizeQuantities($cart->workspace_id, $expected),
                ];
            }

            return $notices;
        });
    }

    /**
     * @param  array<int, int>  $byVariant
     * @return array<string, int>
     */
    private function sizeQuantities(int $workspace, array $byVariant): array
    {
        $sizes = ProductVariant::query()->where('workspace_id', $workspace)->whereIn('id', array_keys($byVariant))->pluck('size', 'id');
        $out = [];
        foreach ($byVariant as $variantId => $qty) {
            $label = (string) ($sizes[$variantId] ?? $variantId);
            $out[$label] = ($out[$label] ?? 0) + (int) $qty;
        }

        return $out;
    }

    public function summary(Cart $cart): array
    {
        $settings = StoreOrderSetting::forWorkspace($cart->workspace_id);
        $precision = $settings->precision();
        $lines = CartItem::query()->where('cart_id', $cart->id)->get();
        $variants = ProductVariant::query()
            ->where('workspace_id', $cart->workspace_id)
            ->whereIn('id', $lines->pluck('variant_id'))
            ->with([
                'product.primaryMedia',
                'product.colors.swatchMedia',
                'product.gallery.media',
                'product.options.values',
                'product.tierPrices',
                'color.swatchMedia',
                'media',
            ])
            ->get()
            ->keyBy('id');

        $groups = [];
        $orderGroups = [];
        $groupErrors = [];
        $modes = [];
        $wholesaleQuantities = [];
        $standardGroupIds = [];
        foreach (CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->get() as $group) {
            $mode = $group->selection['type'] === 'retail' ? 'retail' : 'wholesale';
            $modes[$group->id] = $mode;
            $errors = [];
            $expanded = null;
            try {
                $expanded = app(PackSelectionService::class)->validate($cart->workspace_id, $group->selection);
                foreach ($expanded['orders'] as $orderGroup) {
                    if (isset($orderGroup['pack_snapshot'])) {
                        $orderGroup['pack_snapshot']['cart_group_id'] = $group->id;
                    }
                    $orderGroups[] = $orderGroup;
                }
                $expected = collect($expanded['rows'])->groupBy('variant_id')->map(fn ($r) => $r->sum('quantity'))->sortKeys()->all();
                $actual = $lines->where('group_id', $group->id)->groupBy('variant_id')->map(fn ($r) => $r->sum('quantity'))->sortKeys()->all();
                if ($expected !== $actual) {
                    $errors[] = 'Pack contents have changed. Edit this pack to confirm the new contents.';
                }
            } catch (ValidationException $e) {
                $errors = array_merge(...array_values($e->errors()));
            }
            $groups[] = ['id' => $group->id, 'type' => $group->selection['type'], 'mode' => $mode, 'selection' => $group->selection, 'name' => $expanded['name'] ?? 'Pack needs attention', 'requirements' => $expanded['requirements'] ?? [], 'violations' => $errors];
            foreach ($errors as $error) {
                $groupErrors[] = ['item_id' => 'group-'.$group->id, 'group_id' => $group->id, 'message' => $error];
            }
        }
        $standardPacks = [];
        foreach (CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->get() as $group) {
            if (($group->selection['type'] ?? null) === 'standard_wholesale') {
                $productId = (int) ($group->selection['product_id'] ?? 0);
                $standardPacks[$productId] = ($standardPacks[$productId] ?? 0) + array_sum(array_column($group->selection['colors'] ?? [], 'box_count'));
                $standardGroupIds[$group->id] = true;
            }
        }
        foreach ($lines as $line) {
            if (($modes[$line->group_id] ?? null) === 'wholesale') {
                $wholesaleQuantities[$line->product_id] = ($wholesaleQuantities[$line->product_id] ?? 0) + $line->quantity;
            }
        }
        // Pack-priced products: the price list total for all standard packs, split across those lines.
        $packLineTotals = [];
        foreach ($standardPacks as $productId => $packs) {
            $product = $variants->first(fn ($variant) => $variant->product_id === $productId)?->product;
            $total = $product?->usesPackPricing() ? $product->packTotalMinor($packs, $precision) : null;
            if ($total !== null) {
                $packLines = $lines->filter(fn ($line) => $line->product_id === $productId && isset($standardGroupIds[$line->group_id]));
                $packLineTotals += OrderMoney::allocate($total, $packLines->mapWithKeys(fn ($line) => [$line->id => $line->quantity])->all());
            }
        }
        $requiredStock = $lines->groupBy('variant_id')->map(fn ($rows) => $rows->sum('quantity'));

        if (app(PackSelectionService::class)->physicalBoxCount($orderGroups) > 500) {
            $groupErrors[] = ['item_id' => 'cart', 'message' => 'An order can contain at most 500 boxes.'];
        }
        $items = [];
        $warnings = $groupErrors;
        foreach ($lines as $line) {
            $variant = $variants->get($line->variant_id);
            $p = $variant?->product;
            $mode = $modes[$line->group_id] ?? 'unconfirmed';
            $ws = $mode === 'wholesale';
            if ($mode === 'unconfirmed') {
                $warnings[] = ['item_id' => $line->id, 'message' => 'Confirm this legacy selection as retail, or remove it and rebuild a wholesale pack.'];
            }
            $available = $variant && $p && $p->status === 'active' && $p->visibility === 'published'
                && ($ws ? $p->isWholesaleEnabled() : $p->isRetailEnabled())
                && $variant->status === 'active';
            $stock = $available ? $variant->availableQuantity((int) (ProductVariant::reservedQuantities([$variant->id])[$variant->id] ?? 0)) : 0;
            $tier = $ws && $p ? $p->tierPrices->sortByDesc('min_quantity')->first(fn ($t) => ($wholesaleQuantities[$p->id] ?? 0) >= $t->min_quantity && ($t->max_quantity === null || ($wholesaleQuantities[$p->id] ?? 0) <= $t->max_quantity)) : null;
            $price = $available ? OrderMoney::minor($ws ? ($tier?->unit_price ?? $p->wholesale_price ?? 0) : ($p->single_piece_price ?? $variant->price), $precision) : 0;
            $lineTotal = $price * $line->quantity;
            if ($available && isset($packLineTotals[$line->id])) {
                $lineTotal = $packLineTotals[$line->id];
                $price = intdiv($lineTotal, max(1, $line->quantity));
            }
            if ($ws && $price <= 0) {
                $warnings[] = ['item_id' => $line->id, 'message' => 'Wholesale price is unavailable.'];
            }
            if (! $available || ($requiredStock[$line->variant_id] ?? 0) > $stock) {
                $warnings[] = ['item_id' => $line->id, 'message' => 'This selection is unavailable or exceeds current stock.'];
            }

            $colorImage = $variant?->media?->url
                ?? $variant?->color?->swatchMedia?->url
                ?? ($p && $variant?->color_id ? $p->gallery->firstWhere('color_id', $variant->color_id)?->media?->url : null)
                ?? $p?->primaryMedia?->url;
            $productImage = $p?->primaryMedia?->url ?? $colorImage;
            $orderedRatio = $p && $variant?->color_id ? $this->orderedColorRatio($p, (int) $variant->color_id) : [];
            $piecesPerPack = $orderedRatio !== [] ? array_sum($orderedRatio) : null;

            $items[] = [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'category_id' => $p?->category_id,
                'name' => $p?->name ?? 'Unavailable product',
                'slug' => $p?->slug,
                'image' => $colorImage,
                'product_image' => $productImage,
                'color_id' => $variant?->color_id,
                'color_name' => $variant?->color?->display_name ?? $variant?->color?->name,
                'color_hex' => $variant?->color?->hex_code,
                'color_image' => $colorImage,
                'size' => $variant?->size,
                'sku' => $variant?->sku,
                'retailer_id' => $variant?->meta_retailer_id,
                'quantity' => $line->quantity,
                'price' => OrderMoney::decimal($price, $precision),
                'minor_total' => $available ? $lineTotal : 0,
                'line_total' => OrderMoney::decimal($available ? $lineTotal : 0, $precision),
                'shipping_included' => $ws,
                'stock' => $stock,
                'mode' => $mode,
                'group_id' => $line->group_id,
                'box_count' => null,
                'pieces_per_pack' => $piecesPerPack,
                'store_ratio' => $orderedRatio !== [] ? $orderedRatio : null,
            ];
        }
        $subtotal = array_sum(array_column($items, 'minor_total'));
        $discount = app(CouponService::class)->discount($cart->workspace_id, $cart->coupon_code, $items, $precision, $cart->customer_id);

        foreach ($groups as &$group) {
            $group['items'] = array_values(array_filter($items, fn ($item) => $item['group_id'] === $group['id']));
            $group['total_pieces'] = array_sum(array_column($group['items'], 'quantity'));
            $group['total'] = OrderMoney::decimal(array_sum(array_column($group['items'], 'minor_total')), $precision);
            $group['price'] = $group['total'];
        }
        unset($group);

        return ['groups' => $groups, 'checkout_allowed' => count($items) > 0 && ! $warnings, 'id' => $cart->id, 'items' => $items, 'currency' => $settings->currency, 'precision' => $precision, 'coupon_code' => $cart->coupon_code, 'subtotal' => OrderMoney::decimal($subtotal, $precision), 'discount' => OrderMoney::decimal($discount, $precision), 'shipping_estimate' => null, 'shipping_required' => collect($items)->contains(fn (array $item): bool => $item['mode'] !== 'wholesale'), 'total' => OrderMoney::decimal($subtotal - $discount, $precision), 'warnings' => $warnings];
    }

    public function checkoutGroups(Cart $cart): array
    {
        $orders = [];
        foreach (CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->get() as $group) {
            $expanded = app(PackSelectionService::class)->validate($cart->workspace_id, $group->selection);
            foreach ($expanded['orders'] as $order) {
                if (isset($order['pack_snapshot'])) {
                    $order['pack_snapshot']['cart_group_id'] = $group->id;
                }
                $orders[] = $order;
            }
        }

        return $orders;
    }

    private function orderedColorRatio($product, int $colorId): array
    {
        if (! $product->isWholesaleEnabled()) {
            return [];
        }

        $effective = $product->getEffectiveSizeRatios();
        $raw = $effective[$colorId] ?? $effective[(string) $colorId] ?? [];
        $filtered = array_filter($raw, fn ($qty) => (int) $qty > 0);
        if ($filtered === []) {
            return [];
        }

        $multiplier = max(1, (int) $product->ws_ratio_multiplier);
        $sizeOption = $product->options?->first(fn ($o) => strtolower((string) $o->code) === 'size' || strtolower((string) $o->name) === 'size');
        $preferredOrder = $sizeOption && $sizeOption->relationLoaded('values') && $sizeOption->values->isNotEmpty()
            ? $sizeOption->values->pluck('value')->all()
            : ['XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL', '5XL'];

        $ordered = [];
        foreach ($preferredOrder as $sz) {
            if (array_key_exists($sz, $filtered)) {
                $ordered[$sz] = (int) $filtered[$sz] * $multiplier;
            }
        }
        foreach ($filtered as $sz => $qty) {
            if (! array_key_exists($sz, $ordered)) {
                $ordered[$sz] = (int) $qty * $multiplier;
            }
        }

        return $ordered;
    }
}
