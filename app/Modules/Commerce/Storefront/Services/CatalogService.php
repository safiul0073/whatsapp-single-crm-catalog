<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\StoreFeatures;
use Illuminate\Database\Eloquent\Builder;

class CatalogService
{
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

        $q = Product::query()
            ->where('workspace_id', $workspace)
            ->where('status', 'active')
            ->where('visibility', 'published')
            ->whereIn('selling_mode', $sellingModes)
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
