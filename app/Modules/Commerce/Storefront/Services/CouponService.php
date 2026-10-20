<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\CouponUsage;

class CouponService
{
    public function discount(int $workspace, ?string $code, array $items, int $precision, ?int $customer = null): int
    {
        if (! $code) {
            return 0;
        }
        $c = Coupon::query()->where('workspace_id', $workspace)->where('code', strtoupper($code))->where('active', true)->first();
        abort_unless($c && (! $c->starts_at || $c->starts_at->isPast()) && (! $c->expires_at || $c->expires_at->isFuture()), 422, 'Coupon is unavailable.');
        $uses = CouponUsage::query()->where('workspace_id', $workspace)->where('coupon_id', $c->id);
        abort_if($c->usage_limit !== null && (clone $uses)->count() >= $c->usage_limit, 422, 'Coupon usage limit reached.');
        abort_if($customer && $c->per_customer_limit !== null && (clone $uses)->where('customer_id', $customer)->count() >= $c->per_customer_limit, 422, 'You have already used this coupon.');
        $subtotal = array_sum(array_column($items, 'minor_total'));
        abort_if($subtotal < OrderMoney::minor($c->min_order, $precision), 422, 'Order does not meet the coupon minimum.');
        $eligible = array_sum(array_map(fn ($i) => (! $c->product_ids || in_array($i['product_id'], $c->product_ids)) && (! $c->category_ids || in_array($i['category_id'], $c->category_ids)) ? $i['minor_total'] : 0, $items));
        abort_if($eligible === 0, 422, 'Coupon does not apply to these products.');
        $discount = $c->type === 'percentage' ? (int) floor($eligible * (float) $c->value / 100) : OrderMoney::minor($c->value, $precision);

        return max(0, min($eligible, $discount, $c->max_discount !== null ? OrderMoney::minor($c->max_discount, $precision) : PHP_INT_MAX));
    }
}
