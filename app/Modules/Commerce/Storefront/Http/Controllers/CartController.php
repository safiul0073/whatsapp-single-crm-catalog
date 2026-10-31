<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Storefront\Http\Requests\CartRequest;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartGroup;
use App\Modules\Commerce\Storefront\Models\CartItem;
use App\Modules\Commerce\Storefront\Services\CartService;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            $v = ProductVariant::query()->where('workspace_id', $cart->workspace_id)->whereIn('status', ['active', 'out_of_stock'])->with('product')->lockForUpdate()->findOrFail($r->integer('variant_id'));
            abort_unless($v->product->status === 'active' && $v->product->visibility === 'published' && $v->product->isRetailEnabled(), 422, 'Product unavailable.');
            $group = CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->get()->first(fn ($g) => $g->selection['type'] === 'retail' && (int) $g->selection['variant_id'] === $v->id);
            $group ??= CartGroup::query()->create(['workspace_id' => $cart->workspace_id, 'cart_id' => $cart->id, 'selection' => ['type' => 'retail', 'variant_id' => $v->id, 'quantity' => 0]]);
            $item = CartItem::query()->firstOrNew(['cart_id' => $cart->id, 'group_id' => $group->id, 'variant_id' => $v->id], ['workspace_id' => $cart->workspace_id, 'product_id' => $v->product_id, 'quantity' => 0]);
            abort_if(CartItem::query()->where('cart_id', $cart->id)->count() >= 50 && ! $item->exists, 422, 'Cart is full.');
            $item->quantity += $r->integer('quantity');
            abort_if($item->quantity > 10000, 422);
            $available = $v->availableQuantity(ProductVariant::reservedQuantities([$v->id])[$v->id] ?? 0);
            if ($item->quantity > $available) {
                $this->outOfStock($available);
            }
            $item->price_snapshot = $v->product->single_piece_price ?? $v->price;
            $item->save();
            $group->update(['selection' => ['type' => 'retail', 'variant_id' => $v->id, 'quantity' => $item->quantity]]);

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function update(CartRequest $r, int $id): JsonResponse
    {
        return DB::transaction(function () use ($r, $id): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            $item = CartItem::query()->where('cart_id', $cart->id)->findOrFail($id);
            $group = $item->group_id ? CartGroup::query()->where('cart_id', $cart->id)->findOrFail($item->group_id) : null;
            abort_if($group && $group->selection['type'] !== 'retail', 422, 'Edit the complete pack instead.');
            $variant = ProductVariant::query()->where('workspace_id', $cart->workspace_id)->with('product')->findOrFail($item->variant_id);
            abort_unless($variant->product->isRetailEnabled(), 422, 'Remove this selection and rebuild a wholesale pack.');
            $requested = $r->integer('quantity');
            if ($requested > $item->quantity) {
                $available = $variant->availableQuantity(ProductVariant::reservedQuantities([$variant->id])[$variant->id] ?? 0);
                if ($requested > $available) {
                    $this->outOfStock($available);
                }
            }
            $group ??= CartGroup::query()->create(['workspace_id' => $cart->workspace_id, 'cart_id' => $cart->id, 'selection' => []]);
            $item->update(['quantity' => $r->integer('quantity'), 'group_id' => $group->id]);
            $group->update(['selection' => ['type' => 'retail', 'variant_id' => $item->variant_id, 'quantity' => $item->quantity]]);

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
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

    public function confirmRetail(Request $r, int $id): JsonResponse
    {
        return DB::transaction(function () use ($r, $id): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            $item = CartItem::query()->where('cart_id', $cart->id)->findOrFail($id);
            $group = $item->group_id ? CartGroup::query()->where('cart_id', $cart->id)->findOrFail($item->group_id) : null;
            if ($group && $group->selection['type'] !== 'retail') {
                abort(422, 'This item is part of a wholesale pack. Edit or remove the complete pack.');
            }
            $variant = ProductVariant::query()->where('workspace_id', $cart->workspace_id)->with('product')->findOrFail($item->variant_id);
            abort_unless($variant->product->isRetailEnabled(), 422, 'This product is wholesale only. Remove this selection and rebuild a wholesale pack.');
            $group ??= CartGroup::query()->create(['workspace_id' => $cart->workspace_id, 'cart_id' => $cart->id, 'selection' => []]);
            $item->update(['group_id' => $group->id]);
            $group->update(['selection' => ['type' => 'retail', 'variant_id' => $item->variant_id, 'quantity' => $item->quantity]]);

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function delete(Request $r, int $id): JsonResponse
    {
        return DB::transaction(function () use ($r, $id): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            $item = CartItem::query()->where('cart_id', $cart->id)->findOrFail($id);
            $group = $item->group_id ? CartGroup::query()->where('cart_id', $cart->id)->findOrFail($item->group_id) : null;
            abort_if($group && $group->selection['type'] !== 'retail', 422, 'Remove the complete pack instead.');
            $item->delete();
            $group?->update(['removed_at' => now()]);

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function clear(Request $r): JsonResponse
    {
        return DB::transaction(function () use ($r): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            CartItem::query()->where('cart_id', $cart->id)->delete();
            CartGroup::query()->where('cart_id', $cart->id)->update(['removed_at' => now()]);
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
                abort_if(CartGroup::query()->whereIn('cart_id', [$guest->id, $target->id])->whereNull('removed_at')->count() > 50, 422, 'Remove some packs before merging carts.');
                foreach (CartGroup::query()->where('cart_id', $guest->id)->get() as $group) {
                    $duplicate = $group->request_key ? CartGroup::query()->where('cart_id', $target->id)->where('request_key', $group->request_key)->exists() : false;
                    $group->update(['cart_id' => $target->id, 'request_key' => $duplicate ? null : $group->request_key]);
                }
                CartItem::query()->where('cart_id', $guest->id)->update(['cart_id' => $target->id]);
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
