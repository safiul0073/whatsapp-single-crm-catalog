<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Storefront\Http\Requests\CartRequest;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartItem;
use App\Modules\Commerce\Storefront\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function create(Request $r): JsonResponse
    {
        [$cart,$token] = $this->carts->create($r->attributes->get('store_workspace')->id, $r->attributes->get('store_customer')?->id);

        return response()->json(['data' => [...$this->carts->summary($cart), 'cart_token' => $token]], 201);
    }

    public function show(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->carts->summary($this->carts->resolve($r))]);
    }

    public function add(CartRequest $r): JsonResponse
    {
        return DB::transaction(function () use ($r): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            $v = ProductVariant::query()->where('workspace_id', $cart->workspace_id)->where('status', 'active')->with('product')->findOrFail($r->integer('variant_id'));
            abort_unless($v->product->status === 'active' && $v->product->visibility === 'published' && $v->product->isRetailEnabled(), 422, 'Product unavailable.');
            $item = CartItem::query()->firstOrNew(['cart_id' => $cart->id, 'variant_id' => $v->id], ['workspace_id' => $cart->workspace_id, 'product_id' => $v->product_id, 'quantity' => 0]);
            abort_if(CartItem::query()->where('cart_id', $cart->id)->count() >= 50 && ! $item->exists, 422, 'Cart is full.');
            $item->quantity += $r->integer('quantity');
            abort_if($item->quantity > 10000, 422);
            $item->price_snapshot = $v->product->single_piece_price ?? $v->price;
            $item->save();

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function update(CartRequest $r, int $id): JsonResponse
    {
        return DB::transaction(function () use ($r, $id): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            CartItem::query()->where('cart_id', $cart->id)->findOrFail($id)->update($r->validated());

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function delete(Request $r, int $id): JsonResponse
    {
        return DB::transaction(function () use ($r, $id): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            CartItem::query()->where('cart_id', $cart->id)->findOrFail($id)->delete();

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function clear(Request $r): JsonResponse
    {
        return DB::transaction(function () use ($r): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            CartItem::query()->where('cart_id', $cart->id)->delete();
            $cart->update(['coupon_code' => null]);

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function coupon(CartRequest $r): JsonResponse
    {
        return DB::transaction(function () use ($r): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            $cart->update(['coupon_code' => strtoupper($r->validated('code'))]);

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function removeCoupon(Request $r): JsonResponse
    {
        $cart = $this->carts->resolve($r);
        $cart->update(['coupon_code' => null]);

        return response()->json(['data' => $this->carts->summary($cart)]);
    }

    public function merge(Request $r): JsonResponse
    {
        return DB::transaction(function () use ($r): JsonResponse {
            $customer = $r->attributes->get('store_customer');
            $customer->newQuery()->lockForUpdate()->findOrFail($customer->id);
            $guest = $this->carts->resolve($r, true);
            $target = Cart::query()->where('workspace_id', $customer->workspace_id)->where('customer_id', $customer->id)->whereNull('completed_at')->where('expires_at', '>', now())->whereKeyNot($guest->id)->lockForUpdate()->first();
            if ($target) {
                foreach (CartItem::query()->where('cart_id', $guest->id)->get() as $line) {
                    $item = CartItem::query()->firstOrNew(['cart_id' => $target->id, 'variant_id' => $line->variant_id], ['workspace_id' => $target->workspace_id, 'product_id' => $line->product_id, 'quantity' => 0, 'price_snapshot' => $line->price_snapshot]);
                    $item->quantity = min(10000, $item->quantity + $line->quantity);
                    $item->save();
                }
                $guest->delete();
            } else {
                $target = $guest;
            }
            $token = Str::random(64);
            $target->update(['customer_id' => $customer->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(30)]);

            return response()->json(['data' => [...$this->carts->summary($target), 'cart_token' => $token]]);
        });
    }
}
