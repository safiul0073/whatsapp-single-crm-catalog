<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\CatalogFeedService;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\ActiveThemeResolver;
use App\Modules\Frontend\Services\FrontendPageService;
use App\Modules\Frontend\Services\PageRenderService;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCommerceController extends Controller
{
    public function __construct(
        protected FrontendPageService $pages,
        protected ActiveThemeResolver $activeThemeResolver,
        protected PageRenderService $renderer
    ) {}

    public function feed(string $token, CatalogFeedService $feeds): StreamedResponse
    {
        $catalog = Catalog::query()->where('feed_token', $token)->where('is_active', true)->firstOrFail();

        return $feeds->response($catalog);
    }

    public function directory(): ViewContract
    {
        $workspaces = Workspace::query()
            ->whereHas('products', fn (Builder $query): Builder => $query->where('status', 'active'))
            ->orderBy('name')
            ->paginate(24);

        $payload = $this->frontendPayload(
            title: 'Shops',
            metaDescription: 'Browse merchant storefronts available for WhatsApp ordering.'
        );
        $payload['workspaces'] = $workspaces;

        return view('commerce::public.directory', $payload);
    }

    public function products(Request $request): ViewContract
    {
        $categoryId = $request->integer('category');
        $subcategoryId = $request->integer('subcategory');
        $categoryTree = $this->publicCategoryTree();
        $categoryIds = $this->filteredCategoryIds($categoryId, $subcategoryId, $categoryTree);

        $products = Product::query()
            ->with(['workspace', 'primaryMedia', 'category.parent', 'brandRecord', 'colors', 'tierPrices'])
            ->withMin([
                'variants as starting_price' => fn (Builder $query): Builder => $query->whereIn('status', ['active', 'out_of_stock']),
            ], 'price')
            ->withSum([
                'variants as stock_total' => fn (Builder $query): Builder => $query->where('status', 'active'),
            ], 'stock_quantity')
            ->where('status', 'active')
            ->whereHas('workspace', fn (Builder $query): Builder => $query->where('settings->commerce->shop_enabled', true)->orWhereNull('settings->commerce->shop_enabled'))
            ->when($categoryIds !== [], fn (Builder $query): Builder => $query->whereIn('category_id', $categoryIds))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $workspaceIds = $products->pluck('workspace_id')->unique()->filter()->values()->all();
        $workspacePhones = $this->resolveWorkspacePhones($workspaceIds);

        $payload = $this->frontendPayload(
            title: 'Products',
            metaDescription: 'Browse active products from available WhatsApp storefronts.'
        );
        $payload['products'] = $products;
        $payload['categories'] = $categoryTree;
        $payload['selectedCategoryId'] = $categoryId;
        $payload['selectedSubcategoryId'] = $subcategoryId;
        $payload['currency'] = 'USD';
        $payload['workspacePhones'] = $workspacePhones;

        return view('commerce::public.products', $payload);
    }

    public function index(Workspace $workspace): ViewContract
    {
        abort_unless((bool) data_get($workspace->settings, 'commerce.shop_enabled', true), 404);

        $products = Product::query()
            ->with(['primaryMedia', 'category', 'brandRecord', 'colors', 'tierPrices'])
            ->withCount([
                'variants' => fn (Builder $query): Builder => $query->whereIn('status', ['active', 'out_of_stock']),
            ])
            ->withMin([
                'variants as starting_price' => fn (Builder $query): Builder => $query->whereIn('status', ['active', 'out_of_stock']),
            ], 'price')
            ->withSum([
                'variants as stock_total' => fn (Builder $query): Builder => $query->where('status', 'active'),
            ], 'stock_quantity')
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->orderByDesc('published_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $payload = $this->frontendPayload(
            title: (string) data_get($workspace->settings, 'commerce.storefront_title', $workspace->name),
            metaDescription: (string) data_get($workspace->settings, 'commerce.storefront_description', 'Browse active products available for WhatsApp ordering.')
        );
        $payload['products'] = $products;
        $payload['workspace'] = $workspace;
        $payload['currency'] = $this->currencyFor($workspace);
        $payload['whatsappPhone'] = $this->resolveWorkspaceWhatsAppPhone($workspace->id);

        return view('commerce::public.index', $payload);
    }

    protected function resolveWorkspacePhones(array $workspaceIds): array
    {
        if ($workspaceIds === []) {
            return [];
        }

        $phones = [];

        $catalogs = Catalog::query()
            ->with('channelAccount')
            ->whereIn('workspace_id', $workspaceIds)
            ->where('is_active', true)
            ->get();

        foreach ($catalogs as $catalog) {
            $rawPhone = $catalog->channelAccount?->provider_display_id;
            if ($rawPhone) {
                $cleaned = preg_replace('/\D+/', '', (string) $rawPhone);
                if ($cleaned !== '' && ! isset($phones[$catalog->workspace_id])) {
                    $phones[$catalog->workspace_id] = $cleaned;
                }
            }
        }

        $missing = array_diff($workspaceIds, array_keys($phones));

        if ($missing !== []) {
            $accounts = ChannelAccount::query()
                ->whereIn('workspace_id', $missing)
                ->where('provider', 'whatsapp')
                ->where('status', 'connected')
                ->get();

            foreach ($accounts as $account) {
                if ($account->provider_display_id) {
                    $cleaned = preg_replace('/\D+/', '', (string) $account->provider_display_id);
                    if ($cleaned !== '' && ! isset($phones[$account->workspace_id])) {
                        $phones[$account->workspace_id] = $cleaned;
                    }
                }
            }
        }

        return $phones;
    }

    protected function resolveWorkspaceWhatsAppPhone(int $workspaceId): ?string
    {
        $phones = $this->resolveWorkspacePhones([$workspaceId]);

        return $phones[$workspaceId] ?? null;
    }

    public function legacyProduct(string $product): RedirectResponse
    {
        $matches = Product::query()
            ->with('workspace')
            ->where('slug', $product)
            ->where('status', 'active')
            ->limit(2)
            ->get();

        abort_unless($matches->count() === 1, 404);

        $record = $matches->first();

        return redirect()->route('commerce.products.public', [
            'workspace' => $record->workspace->slug,
            'product' => $record->slug,
        ], 301);
    }

    public function directProduct(string $product): ViewContract
    {
        $record = Product::query()
            ->with('workspace')
            ->where('slug', $product)
            ->first();

        if (! $record && is_numeric($product)) {
            $record = Product::query()->with('workspace')->find((int) $product);
        }

        abort_unless($record && $record->workspace, 404);

        return $this->product($record->workspace, $record);
    }

    public function product(Workspace $workspace, Product $product): ViewContract
    {
        abort_unless((bool) data_get($workspace->settings, 'commerce.shop_enabled', true), 404);
        abort_unless($product->workspace_id === $workspace->id, 404);

        $query = Product::query()
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
            ->whereKey($product->id)
            ->where('workspace_id', $workspace->id);

        $isOwnerOrStaff = auth()->check() && (auth()->user()->workspaces()->where('workspace_id', $workspace->id)->exists() || (bool) (auth()->user()->is_superadmin ?? false));

        if (! $isOwnerOrStaff) {
            $query->where('status', 'active');
        }

        $record = $query->firstOrFail();

        $phone = $this->resolveWorkspaceWhatsAppPhone($workspace->id);

        $options = $record->options->map(fn ($option) => [
            'id' => $option->id,
            'name' => $option->name,
            'code' => $option->code,
            'values' => $option->values->pluck('value')->values()->all(),
        ])->values();

        $relatedProducts = Product::query()
            ->with(['workspace', 'primaryMedia', 'category', 'brandRecord'])
            ->withMin([
                'variants as starting_price' => fn (Builder $query): Builder => $query->whereIn('status', ['active', 'out_of_stock']),
            ], 'price')
            ->withSum([
                'variants as stock_total' => fn (Builder $query): Builder => $query->where('status', 'active'),
            ], 'stock_quantity')
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->where('id', '!=', $record->id)
            ->latest('id')
            ->take(4)
            ->get();

        $catalog = Catalog::query()->where('workspace_id', $record->workspace_id)->where('is_active', true)->first();

        $payload = $this->frontendPayload(
            title: $record->name,
            metaDescription: str($record->description)->limit(155)->toString()
        );
        $payload['product'] = $record;
        $payload['whatsappPhone'] = $phone;
        $payload['workspace'] = $workspace;
        $payload['currency'] = $catalog?->currency ?: $this->currencyFor($workspace);
        $payload['options'] = $options;
        $payload['relatedProducts'] = $relatedProducts;

        return view('commerce::public.product', $payload);
    }

    protected function currencyFor(Workspace $workspace): string
    {
        return strtoupper((string) data_get($workspace->settings, 'commerce.currency', 'USD'));
    }

    protected function frontendPayload(string $title, string $metaDescription): array
    {
        $page = $this->pages->findBySlug('shop') ?? new Page([
            'title' => $title,
            'slug' => 'shop',
            'status' => 'published',
            'default_layout' => 'default',
            'meta_title' => $title,
            'meta_description' => $metaDescription,
        ]);

        return $this->renderer->payload($page, $this->activeThemeResolver->resolve());
    }

    protected function publicCategoryTree(): Collection
    {
        $activeCategoryIds = Product::query()
            ->where('status', 'active')
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id');

        $parentCategoryIds = Category::query()
            ->whereIn('id', $activeCategoryIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id');

        $visibleCategoryIds = $activeCategoryIds->merge($parentCategoryIds)->unique()->values();

        return Category::query()
            ->with(['children' => fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('id', $activeCategoryIds)
                ->orderBy('name')])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereIn('id', $visibleCategoryIds)
            ->orderBy('name')
            ->get();
    }

    protected function filteredCategoryIds(int $categoryId, int $subcategoryId, Collection $categoryTree): array
    {
        if ($subcategoryId > 0) {
            return [$subcategoryId];
        }

        if ($categoryId <= 0) {
            return [];
        }

        $category = $categoryTree->firstWhere('id', $categoryId);

        if (! $category) {
            return [];
        }

        return collect([$category->id])
            ->merge($category->children->pluck('id'))
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
