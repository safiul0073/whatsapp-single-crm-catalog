<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Product;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Container\Attributes\Scoped;

#[Scoped]
class ProductShowcaseLink
{
    private ?Workspace $workspace;

    public function __construct()
    {
        $this->workspace = Workspace::query()->where('status', 'active')
            ->when(config('commerce.store_workspace_id'), fn ($query, $id) => $query->whereKey($id))->oldest('id')->first();
    }

    public function url(Product $product): ?string
    {
        $base = rtrim((string) config('commerce.showcase_frontend_url'), '/');
        if (! filter_var($base, FILTER_VALIDATE_URL) || ! in_array(parse_url($base, PHP_URL_SCHEME), ['https', 'http'], true)
            || parse_url($base, PHP_URL_QUERY) || parse_url($base, PHP_URL_FRAGMENT)
            || $product->workspace_id !== $this->workspace?->id || $product->status !== 'active' || $product->visibility !== 'published'
            || ! data_get($this->workspace?->settings, 'commerce.shop_enabled', true)) {
            return null;
        }

        return $base.'/share/product/'.rawurlencode($product->slug);
    }
}
