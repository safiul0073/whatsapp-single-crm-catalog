<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\ProductOption;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\ProductTypeService;
use App\Modules\Commerce\Storefront\Http\Requests\CatalogRequest;
use App\Modules\Commerce\Storefront\Http\Resources\ProductResource;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalog, private ProductTypeService $productTypes) {}

    public function index(CatalogRequest $r): AnonymousResourceCollection
    {
        $q = $this->catalog->query($r->attributes->get('store_workspace')->id, $r->validated());
        $sort = $r->input('sort');
        if (in_array($sort, ['price_asc', 'price_desc'])) {
            $q->orderByRaw('COALESCE(single_piece_price, (SELECT MIN(price) FROM commerce_product_variants WHERE product_id = commerce_products.id)) '.($sort === 'price_asc' ? 'ASC' : 'DESC'));
        } elseif ($sort === 'name_asc') {
            $q->orderBy('name');
        }

        return ProductResource::collection($q->orderByDesc('id')->paginate($r->integer('per_page', 16)));
    }

    public function show(Request $r, string $slug): ProductResource
    {
        return new ProductResource($this->catalog->query($r->attributes->get('store_workspace')->id, ['selling_mode' => 'all'])->with('gallery.media')->where('slug', $slug)->firstOrFail());
    }

    public function categories(Request $r): JsonResponse
    {
        $workspace = $r->attributes->get('store_workspace')->id;
        $all = Category::query()->where('workspace_id', $workspace)->where('is_active', true)->orderBy('name')->get();
        $data = $all->map(function (Category $category) use ($workspace): array {
            $type = $this->productTypes->forCategory($category, $workspace);

            return $category->only(['id', 'name', 'slug', 'parent_id', 'image']) + ['product_type' => ['slug' => $type->slug, 'layout' => $type->layout]];
        });

        return response()->json(['data' => $data]);
    }

    public function category(Request $r, string $slug): JsonResponse
    {
        return response()->json(['data' => Category::query()->where('workspace_id', $r->attributes->get('store_workspace')->id)->where('is_active', true)->where('slug', $slug)->firstOrFail()]);
    }

    public function brands(Request $r): JsonResponse
    {
        return response()->json(['data' => Brand::query()->where('workspace_id', $r->attributes->get('store_workspace')->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])]);
    }

    public function menu(Request $r): JsonResponse
    {
        return response()->json(['data' => ['categories' => $this->categories($r)->getData(true)['data'], 'brands' => $this->brands($r)->getData(true)['data']]]);
    }

    public function suggest(CatalogRequest $r): AnonymousResourceCollection
    {
        return ProductResource::collection($this->catalog->query($r->attributes->get('store_workspace')->id, $r->validated())->limit(8)->get());
    }

    public function collection(CatalogRequest $r, string $key): AnonymousResourceCollection
    {
        $workspace = $r->attributes->get('store_workspace')->id;
        $q = $this->catalog->query($workspace, $r->validated());
        if ($key === 'deals') {
            $q->whereHas('variants', fn ($variants) => $variants->where('status', 'active')->whereColumn('compare_at_price', '>', 'price'));
        } elseif ($key !== 'new-arrivals') {
            $collection = StorefrontContent::query()->where('workspace_id', $workspace)->where('type', 'collection')->where('slug', $key)->where('published_at', '<=', now())->first();
            abort_unless($collection, 404);
            $q->whereIn('id', $collection->data['product_ids'] ?? []);
        }

        return ProductResource::collection($q->orderByDesc('id')->paginate($r->integer('per_page', 16)));
    }

    public function filters(CatalogRequest $r): JsonResponse
    {
        $id = $r->attributes->get('store_workspace')->id;
        $q = $this->catalog->query($id, $r->validated());
        $ids = (clone $q)->select('commerce_products.id')->setEagerLoads([]);
        $categories = Category::query()->where('workspace_id', $id)->where('is_active', true)->withCount(['products' => fn ($p) => $p->whereIn('id', $ids)])->get(['id', 'name', 'slug']);
        $sizes = ProductVariant::query()->where('workspace_id', $id)->whereIn('product_id', $ids)->where('status', 'active')->whereNotNull('size')->selectRaw('size, COUNT(DISTINCT product_id) as count')->groupBy('size')->get();

        $category = $r->filled('category') ? Category::query()->where('workspace_id', $id)->where('slug', $r->input('category'))->first() : null;
        $type = $category ? $this->productTypes->forCategory($category, $id) : null;
        $attributes = [];
        foreach (collect($type?->attributes ?? [])->where('filterable', true) as $definition) {
            $counts = (clone $q)->setEagerLoads([])->pluck('attribute_values')
                ->map(fn ($values) => $values[$definition['key']] ?? null)
                ->filter(fn ($value) => filled($value) || $value === 0)
                ->countBy()
                ->map(fn (int $count, $value) => ['value' => (string) $value, 'count' => $count])
                ->values();
            if ($counts->isNotEmpty()) {
                $attributes[] = ['key' => $definition['key'], 'label' => $definition['label'], 'values' => $counts];
            }
        }
        $options = ProductOption::query()->whereIn('product_id', $ids)->with('values')->get()
            ->groupBy('code')
            ->map(fn ($group, $code) => [
                'code' => $code,
                'name' => $group->first()->name,
                'values' => $group->flatMap(fn ($option) => $option->values->pluck('value'))->countBy()->map(fn (int $count, $value) => ['value' => (string) $value, 'count' => $count])->values(),
            ])
            ->values();

        return response()->json(['data' => [
            'categories' => $categories,
            'sizes' => $sizes,
            'brands' => $this->brands($r)->getData(true)['data'],
            'product_type' => $type ? ['slug' => $type->slug, 'layout' => $type->layout] : null,
            'attributes' => $attributes,
            'options' => $options,
        ]]);
    }

    public function sitemap(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->catalog->query($r->attributes->get('store_workspace')->id)->select('id', 'slug', 'updated_at')->setEagerLoads([])->orderBy('id')->paginate(1000)]);
    }
}
