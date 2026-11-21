<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\MetaConversionsService;
use App\Modules\Commerce\Storefront\Http\Requests\CartRequest;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartGroup;
use App\Modules\Commerce\Storefront\Models\CartItem;
use App\Modules\Commerce\Storefront\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function __construct(private CartService $carts, private MetaConversionsService $conversions) {}

    public function create(Request $r): JsonResponse
    {
        [$cart,$token] = $this->carts->create($r->attributes->get('store_workspace')->id, $r->attributes->get('store_customer')?->id);

        return response()->json(['data' => [...$this->carts->summary($cart), 'cart_token' => $token]], 201);
    }

    public function show(Request $r): JsonResponse
    {
        $cart = $this->carts->resolve($r);
        $notices = $this->carts->refreshStalePacks($cart);

        return response()->json(['data' => [...$this->carts->summary($cart), 'notices' => $notices]]);
    }

    public function add(CartRequest $r): JsonResponse
    {
        return DB::transaction(function () use ($r): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            $item = $this->carts->addRetail($cart, $r->integer('variant_id'), $r->integer('quantity'));
            $summary = $this->carts->summary($cart);
            $line = collect($summary['items'])->first(fn (array $i): bool => (int) $i['variant_id'] === $item->variant_id && (int) $i['group_id'] === $item->group_id);
            if ($line) {
                $this->conversions->queueAddToCart($r, $summary, [array_merge($line, ['quantity' => $r->integer('quantity')])]);
            }

            return response()->json(['data' => $summary]);
        });
    }

    public function update(CartRequest $r, int $id): JsonResponse
    {
        return DB::transaction(function () use ($r, $id): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            $this->carts->setRetailQuantity($cart, $id, $r->integer('quantity'));

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
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
            $this->carts->removeLine($cart, $id);

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }

    public function clear(Request $r): JsonResponse
    {
        return DB::transaction(function () use ($r): JsonResponse {
            $cart = $this->carts->resolve($r, true);
            $this->carts->clear($cart);

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
