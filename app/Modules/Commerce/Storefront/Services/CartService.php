<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartGroup;
use App\Modules\Commerce\Storefront\Models\CartItem;
use Illuminate\Http\Request;
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
        foreach ($lines as $line) {
            if (($modes[$line->group_id] ?? null) === 'wholesale') {
                $wholesaleQuantities[$line->product_id] = ($wholesaleQuantities[$line->product_id] ?? 0) + $line->quantity;
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
            $stock = $available ? max(0, $variant->stock_quantity - (int) OrderReservation::query()->where('variant_id', $variant->id)->where('state', 'reserved')->where('expires_at', '>', now())->sum('quantity')) : 0;
            $tier = $ws && $p ? $p->tierPrices->sortByDesc('min_quantity')->first(fn ($t) => ($wholesaleQuantities[$p->id] ?? 0) >= $t->min_quantity && ($t->max_quantity === null || ($wholesaleQuantities[$p->id] ?? 0) <= $t->max_quantity)) : null;
            $price = $available ? OrderMoney::minor($ws ? ($tier?->unit_price ?? $p->wholesale_price ?? 0) : ($p->single_piece_price ?? $variant->price), $precision) : 0;
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
                'quantity' => $line->quantity,
                'price' => OrderMoney::decimal($price, $precision),
                'minor_total' => $price * $line->quantity,
                'line_total' => OrderMoney::decimal($price * $line->quantity, $precision),
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

        return ['groups' => $groups, 'checkout_allowed' => count($items) > 0 && ! $warnings, 'id' => $cart->id, 'items' => $items, 'currency' => $settings->currency, 'precision' => $precision, 'coupon_code' => $cart->coupon_code, 'subtotal' => OrderMoney::decimal($subtotal, $precision), 'discount' => OrderMoney::decimal($discount, $precision), 'shipping_estimate' => null, 'total' => OrderMoney::decimal($subtotal - $discount, $precision), 'warnings' => $warnings];
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
