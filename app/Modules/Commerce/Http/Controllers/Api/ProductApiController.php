<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductVariant;
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
        $query = Product::query()
            ->with(['primaryMedia', 'category', 'brandRecord', 'colors', 'variants', 'options'])
            ->withMin([
                'variants as starting_price' => fn ($q) => $q->whereIn('status', ['active', 'out_of_stock']),
            ], 'price')
            ->where('status', 'active');

        // Apply Filters
        // Apply Filters
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('brand')) {
            $brands = is_array($request->brand) ? $request->brand : explode(',', $request->brand);
            $query->whereHas('brandRecord', function ($q) use ($brands) {
                $q->whereIn('slug', $brands)->orWhereIn('name', $brands);
            });
        }

        if ($request->filled('category') || $request->filled('subcategory')) {
            $cats = $request->filled('category') ? $request->category : $request->subcategory;
            $catArray = is_array($cats) ? $cats : explode(',', $cats);

            // Fetch matched categories
            $matchedCats = Category::whereIn('slug', $catArray)
                ->orWhereIn('name', $catArray)
                ->orWhereIn('id', $catArray)
                ->pluck('id')->toArray();

            if (!empty($matchedCats)) {
                // Get Level 2 children
                $childIds = Category::whereIn('parent_id', $matchedCats)->pluck('id')->toArray();
                // Get Level 3 children
                $grandchildIds = !empty($childIds) ? Category::whereIn('parent_id', $childIds)->pluck('id')->toArray() : [];
                
                $allCategoryIds = array_unique(array_merge($matchedCats, $childIds, $grandchildIds));

                $query->whereIn('category_id', $allCategoryIds);
            }
        }

        if ($request->filled('color')) {
            $query->whereHas('colors', fn($q) => $q->where('name', $request->color)->orWhere('hex_code', $request->color));
        }

        if ($request->filled('size')) {
            $query->whereHas('variants', fn($q) => $q->where('size', $request->size));
        }

        if ($request->filled('min_price')) {
            $query->where(function($q) use ($request) {
                $q->where('single_piece_price', '>=', $request->min_price)
                  ->orWhereHas('variants', fn($qv) => $qv->where('price', '>=', $request->min_price));
            });
        }

        if ($request->filled('max_price')) {
            $query->where(function($q) use ($request) {
                $q->where('single_piece_price', '<=', $request->max_price)
                  ->orWhereHas('variants', fn($qv) => $qv->where('price', '<=', $request->max_price));
            });
        }

        // Apply Sorting
        $sort = $request->get('sort', 'relevance');
        switch ($sort) {
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'price_asc':
                $query->orderBy('single_piece_price'); // simplified for API
                break;
            case 'price_desc':
                $query->orderByDesc('single_piece_price');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            default:
                $query->orderByDesc('id');
                break;
        }

        $products = $query->paginate($request->integer('per_page', 16));

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


    /**
     * Retrieve all available filters (Categories, Brands, Colors, etc.)
     */
    public function filters(Request $request)
    {
        $workspaceId = $request->get('workspace_id', 1); // Defaults to 1 for demo purposes if not passed

        // Fetch hierarchical categories
        $categories = Category::where('workspace_id', $workspaceId)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->with(['children' => function($q2) {
                    $q2->where('is_active', true);
                }]);
            }])
            ->orderBy('name')
            ->get();

        // Fetch brands
        $brands = Brand::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Colors
        $colors = ProductColor::where('workspace_id', $workspaceId)
            ->select('name', 'hex_code')
            ->distinct()
            ->get();

        // Sizes (from variants)
        $sizes = ProductVariant::where('workspace_id', $workspaceId)
            ->whereNotNull('size')
            ->select('size')
            ->distinct()
            ->pluck('size');

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'brands' => $brands,
                'colors' => $colors,
                'sizes' => $sizes
            ]
        ]);
    }
}
