<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\StoreFeatures;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogService
{
    /** Order statuses that do not count as a real sale. */
    public const NON_SALE_STATUSES = ['cancelled', 'refunded', 'failed', 'returned', 'draft'];

    /** Recent sales window that ranks "Top Sellers" and the best seller badge. */
    public const BEST_SELLER_WINDOW_DAYS = 90;

    /** Smallest sold count worth showing as social proof ("🔥 N sold", best seller badge). */
    public static function soldBadgeMinimum(): int
    {
        return max(1, (int) config('storefront.sold_badge_min', 10));
    }

    /** Units sold per product (product_id, sold_qty) across real sales, optionally only since a date. */
    public function soldQuantities(int $workspace, ?CarbonInterface $since = null): QueryBuilder
    {
        return DB::table('commerce_order_items as oi')
            ->join('commerce_orders as o', 'o.id', '=', 'oi.order_id')
            ->join('commerce_product_variants as v', 'v.id', '=', 'oi.variant_id')
            ->where('o.workspace_id', $workspace)
            ->whereNotIn('o.status', self::NON_SALE_STATUSES)
            ->whereNull('o.deleted_at')
            ->when($since, fn (QueryBuilder $q): QueryBuilder => $q->where('o.created_at', '>=', $since))
            ->groupBy('v.product_id')
            ->selectRaw('v.product_id, SUM(oi.quantity) as sold_qty');
    }

    /**
     * Published products that sold best in the recent window and passed the badge minimum.
     *
     * @return list<int>
     */
    public function bestSellerIds(int $workspace): array
    {
        return Cache::memo()->remember('storefront_best_sellers_'.$workspace, 600, fn (): array => DB::query()
            ->fromSub($this->soldQuantities($workspace, now()->subDays(self::BEST_SELLER_WINDOW_DAYS)), 'sold')
            ->join('commerce_products as p', 'p.id', '=', 'sold.product_id')
            ->where('p.status', 'active')
            ->where('p.visibility', 'published')
            ->where('sold.sold_qty', '>=', self::soldBadgeMinimum())
            ->orderByDesc('sold.sold_qty')
            ->orderByDesc('sold.product_id')
            ->limit(max(1, (int) config('storefront.best_seller_count', 10)))
            ->pluck('sold.product_id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }

    public function query(int $workspace, array $filters = []): Builder
    {
        $sellingModes = match ($filters['selling_mode'] ?? null) {
            'wholesale' => ['wholesale', 'both'],
            'retail' => ['retail', 'both'],
            'all' => ['retail', 'wholesale', 'both'],
            default => ['retail', 'both'],
        };
        if (! StoreFeatures::wholesaleEnabled($workspace)) {
            $sellingModes = ['retail', 'both'];
        }
        $retailOn = StoreFeatures::retailEnabled($workspace);
        if (! $retailOn) {
            $sellingModes = ['wholesale', 'both'];
        }

        $q = Product::query()
            ->where('workspace_id', $workspace)
            ->where('status', 'active')
            ->where('visibility', 'published')
            ->whereIn('selling_mode', $sellingModes)
            ->when(! $retailOn, fn (Builder $query): Builder => $query->where('ws_enabled', true))
            ->select('commerce_products.*')
            ->selectSub(
                OrderItem::query()
                    ->selectRaw('COALESCE(SUM(commerce_order_items.quantity), 0)')
                    ->join('commerce_orders', 'commerce_orders.id', '=', 'commerce_order_items.order_id')
                    ->join('commerce_product_variants', 'commerce_product_variants.id', '=', 'commerce_order_items.variant_id')
                    ->whereColumn('commerce_product_variants.product_id', 'commerce_products.id')
                    ->where('commerce_orders.workspace_id', $workspace)
                    ->whereNotIn('commerce_orders.status', self::NON_SALE_STATUSES)
                    ->whereNull('commerce_orders.deleted_at'),
                'sold_count',
            )
            ->with(['primaryMedia', 'category', 'brandRecord', 'colors.swatchMedia', 'variants', 'options.values', 'tierPrices']);
        $search = $filters['q'] ?? $filters['search'] ?? null;
        if (! empty($search)) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%'.$search.'%')
                    ->orWhere('brand', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%');
            });
        }
        if (! empty($filters['category'])) {
            $ids = Category::query()->where('workspace_id', $workspace)->where('slug', $filters['category'])->pluck('id')->all();
            $front = $ids;
            for ($depth = 0; $depth < 20 && $front; $depth++) {
                $front = Category::query()->where('workspace_id', $workspace)->whereIn('parent_id', $front)->whereNotIn('id', $ids)->pluck('id')->all();
                $ids = array_merge($ids, $front);
            }
            $q->whereIn('category_id', $ids);
        }
        if (! empty($filters['brand'])) {
            $q->whereHas('brandRecord', fn ($b) => $b->where('workspace_id', $workspace)->where('slug', $filters['brand']));
        }
        if (! empty($filters['color'])) {
            $q->whereHas('colors', fn ($c) => $c->where('name', $filters['color']));
        }
        if (! empty($filters['size'])) {
            $q->whereHas('variants', fn ($v) => $v->where('status', 'active')->where('size', $filters['size']));
        }
        foreach ((array) ($filters['attr'] ?? []) as $key => $value) {
            if (is_string($key) && preg_match('/^[a-z0-9_]{1,60}$/', $key) && filled($value)) {
                $q->where('attribute_values->'.$key, $value);
            }
        }
        foreach ((array) ($filters['option'] ?? []) as $code => $value) {
            if (is_string($code) && preg_match('/^[a-z0-9_]{1,60}$/', $code) && filled($value)) {
                $q->whereHas('options', fn ($o) => $o->where('code', $code)->whereHas('values', fn ($v) => $v->where('value', $value)));
            }
        }
        if (! empty($filters['in_stock'])) {
            $q->whereHas('variants', fn ($v) => $v->where('status', 'active')->where('stock_quantity', '>', 0));
        }
        foreach (['price_min' => '>=', 'price_max' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $q->whereRaw('COALESCE(single_piece_price, (SELECT MIN(price) FROM commerce_product_variants WHERE product_id = commerce_products.id AND status = ?)) '.$operator.' ?', ['active', $filters[$key]]);
            }
        }

        return $q;
    }
}
