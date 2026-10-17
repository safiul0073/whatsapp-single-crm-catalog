<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Storefront\Http\Requests\CheckoutRequest;
use App\Modules\Commerce\Storefront\Models\CheckoutSubmission;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\CouponUsage;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Services\CartService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(private CartService $carts, private UnifiedOrderService $orders) {}

    public function methods(Request $r): JsonResponse
    {
        return response()->json(['data' => StoreOrderSetting::paymentMethods($r->attributes->get('store_workspace')->id, true)]);
    }

    private function data(CheckoutRequest $r): array
    {
        $c = $r->attributes->get('store_customer');
        $cart = $this->carts->resolve($r, true);
        abort_unless($cart->customer_id === $c->id, 422, 'Merge your cart before checkout.');
        $s = $this->carts->summary($cart);
        abort_unless(count($s['items']) > 0 && ! $s['warnings'], 422, 'Review unavailable items in your cart.');
        $a = CustomerAddress::query()->where('workspace_id', $c->workspace_id)->where('customer_id', $c->id)->findOrFail($r->integer('address_id'));
        $data = ['source' => 'storefront_checkout', 'draft' => false, 'contact_id' => $c->contact_id, 'customer_reference' => 'storefront:'.$c->id, 'shipping_address' => $a->address, 'shipping_method_id' => $r->input('shipping_method_id'), 'groups' => array_map(fn ($i) => ['product_id' => $i['product_id'], 'variant_id' => $i['variant_id'], 'mode' => 'retail', 'quantity' => $i['quantity']], $s['items'])];
        if ($cart->coupon_code) {
            $data['adjustments'] = [['type' => 'coupon', 'amount' => $s['discount'], 'currency' => $s['currency'], 'reference' => $cart->coupon_code]];
        }

        return [$cart, $data];
    }

    public function preview(CheckoutRequest $r): JsonResponse
    {
        return DB::transaction(function () use ($r): JsonResponse {
            [, $data] = $this->data($r);

            return response()->json(['data' => $this->orders->preview($r->attributes->get('store_workspace'), $data)]);
        });
    }

    public function shippingMethods(CheckoutRequest $r): JsonResponse
    {
        [, $data] = $this->data($r);
        $preview = $this->orders->preview($r->attributes->get('store_workspace'), $data);

        return response()->json(['data' => $preview['shipping_options']]);
    }

    public function store(CheckoutRequest $r): JsonResponse
    {
        $key = (string) $r->header('Idempotency-Key');
        abort_unless(Str::isUuid($key), 422, 'Idempotency-Key must be a UUID.');

        return DB::transaction(function () use ($r, $key): JsonResponse {
            $w = $r->attributes->get('store_workspace');
            $c = $r->attributes->get('store_customer');
            Workspace::query()->lockForUpdate()->findOrFail($w->id);
            $hash = hash('sha256', json_encode([$r->validated(), hash('sha256', (string) $r->header('X-Cart-Token'))], JSON_THROW_ON_ERROR));
            $submission = CheckoutSubmission::query()->where('workspace_id', $w->id)->where('customer_id', $c->id)->where('key', $key)->first();
            if ($submission) {
                abort_unless(hash_equals($submission->request_hash, $hash), 409, 'This idempotency key has different contents.');

                return response()->json(['data' => $this->orders->payload(Order::query()->where('workspace_id', $w->id)->findOrFail($submission->order_id))]);
            }
            $method = collect(StoreOrderSetting::paymentMethods($w->id, true))->firstWhere('id', $r->input('payment_method_id'));
            abort_unless($method, 422, 'Choose an available manual payment method.');
            [$cart,$data] = $this->data($r);
            $data['submission_reference'] = $key;
            $preview = $this->orders->preview($w, $data);
            abort_if($preview['shipping_quote_required'], 422, 'No shipping rate covers this address.');
            $order = $this->orders->create($w, $data);
            $order->update(['provider_payload' => array_merge($order->provider_payload ?? [], ['manual_payment_method_id' => $method['id']])]);
            CheckoutSubmission::query()->create(['workspace_id' => $w->id, 'customer_id' => $c->id, 'key' => $key, 'request_hash' => $hash, 'payload' => $data, 'order_id' => $order->id]);
            if ($cart->coupon_code) {
                $coupon = Coupon::query()->where('workspace_id', $w->id)->where('code', $cart->coupon_code)->firstOrFail();
                CouponUsage::query()->create(['workspace_id' => $w->id, 'coupon_id' => $coupon->id, 'customer_id' => $c->id, 'order_id' => $order->id]);
            }
            $cart->update(['completed_at' => now()]);

            return response()->json(['data' => $this->orders->payload($order)], 201);
        }, 3);
    }
}
