<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Http\Resources\ProductResource;
use App\Modules\Commerce\Http\Resources\ProductDetailResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductApiController extends Controller
{
    /**
     * Retrieve a paginated list of all active products.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(['primaryMedia', 'category', 'brandRecord'])
            ->withMin([
                'variants as starting_price' => fn ($query) => $query->whereIn('status', ['active', 'out_of_stock']),
            ], 'price')
            ->where('status', 'active')
            ->latest('id')
            ->paginate($request->integer('per_page', 12));

        return ProductResource::collection($products);
    }

    /**
     * Retrieve the latest 4 products to be showcased as new deals.
     */
    public function deals(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(['primaryMedia', 'category', 'brandRecord'])
            ->withMin([
                'variants as starting_price' => fn ($query) => $query->whereIn('status', ['active', 'out_of_stock']),
            ], 'price')
            ->where('status', 'active')
            ->orderByDesc('published_at')
            ->latest('id')
            ->take(4)
            ->get();

        return ProductResource::collection($products);
    }

    /**
     * Retrieve full details of a specific product for checkout.
     */
    public function show(Request $request, string $product): ProductDetailResource
    {
        $record = Product::query()
            ->with([
                'primaryMedia',
                'category',
                'brandRecord',
                'gallery.media',
                'colors.swatchMedia',
                'tierPrices',
                'options.values',
                'variants' => fn ($query) => $query->whereIn('status', ['active', 'out_of_stock'])->orderBy('price'),
                'variants.media',
                'variants.color',
            ])
            ->where('status', 'active')
            ->where(function ($q) use ($product) {
                $q->where('slug', $product)->orWhere('id', $product);
            })
            ->firstOrFail();

        return new ProductDetailResource($record);
    }
}
