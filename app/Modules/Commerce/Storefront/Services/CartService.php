<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $items = [];
        $warnings = [];
        foreach (CartItem::query()->where('cart_id', $cart->id)->get() as $line) {
            $variant = ProductVariant::query()->where('workspace_id', $cart->workspace_id)->with('product.primaryMedia')->find($line->variant_id);
            $p = $variant?->product;
            $available = $variant && $p && $p->status === 'active' && $p->visibility === 'published' && $p->isRetailEnabled() && $variant->status === 'active';
            $stock = $available ? max(0, $variant->stock_quantity - (int) OrderReservation::query()->where('variant_id', $variant->id)->where('state', 'reserved')->where('expires_at', '>', now())->sum('quantity')) : 0;
            $price = $available ? OrderMoney::minor($p->single_piece_price ?? $variant->price, $precision) : 0;
            if (! $available || $line->quantity > $stock) {
                $warnings[] = ['item_id' => $line->id, 'message' => 'This selection is unavailable or exceeds current stock.'];
            }
            $items[] = ['id' => $line->id, 'product_id' => $line->product_id, 'variant_id' => $line->variant_id, 'category_id' => $p?->category_id, 'name' => $p?->name ?? 'Unavailable product', 'slug' => $p?->slug, 'image' => $p?->primaryMedia?->url, 'size' => $variant?->size, 'quantity' => $line->quantity, 'price' => OrderMoney::decimal($price, $precision), 'minor_total' => $price * $line->quantity, 'line_total' => OrderMoney::decimal($price * $line->quantity, $precision), 'stock' => $stock];
        }
        $subtotal = array_sum(array_column($items, 'minor_total'));
        $discount = app(CouponService::class)->discount($cart->workspace_id, $cart->coupon_code, $items, $precision, $cart->customer_id);

        return ['id' => $cart->id, 'items' => $items, 'currency' => $settings->currency, 'precision' => $precision, 'coupon_code' => $cart->coupon_code, 'subtotal' => OrderMoney::decimal($subtotal, $precision), 'discount' => OrderMoney::decimal($discount, $precision), 'shipping_estimate' => null, 'total' => OrderMoney::decimal($subtotal - $discount, $precision), 'warnings' => $warnings];
    }
}
