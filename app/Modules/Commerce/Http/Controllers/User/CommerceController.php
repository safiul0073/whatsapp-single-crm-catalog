<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\AudienceRequest;
use App\Modules\Commerce\Http\Requests\BrandRequest;
use App\Modules\Commerce\Http\Requests\BulkDeleteCommerceRequest;
use App\Modules\Commerce\Http\Requests\CatalogRequest;
use App\Modules\Commerce\Http\Requests\CategoryRequest;
use App\Modules\Commerce\Http\Requests\CommerceSettingsRequest;
use App\Modules\Commerce\Http\Requests\ProductDetailsRequest;
use App\Modules\Commerce\Http\Requests\ProductGalleryRequest;
use App\Modules\Commerce\Http\Requests\ProductOptionsRequest;
use App\Modules\Commerce\Http\Requests\ProductRequest;
use App\Modules\Commerce\Http\Requests\ProductVariantsRequest;
use App\Modules\Commerce\Http\Requests\PublishProductRequest;
use App\Modules\Commerce\Http\Requests\QuoteOrderRequest;
use App\Modules\Commerce\Http\Requests\SendProductListRequest;
use App\Modules\Commerce\Http\Requests\SendProductMessageRequest;
use App\Modules\Commerce\Http\Requests\SendProductVideoRequest;
use App\Modules\Commerce\Http\Requests\TransitionOrderRequest;
use App\Modules\Commerce\Http\Requests\UploadCommerceMediaRequest;
use App\Modules\Commerce\Models\Audience;
use App\Modules\Commerce\Models\Brand;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\CatalogDiagnosticsService;
use App\Modules\Commerce\Services\CatalogMessageService;
use App\Modules\Commerce\Services\CatalogSyncService;
use App\Modules\Commerce\Services\MetaCatalogClient;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\ProductReadinessService;
use App\Modules\Commerce\Services\ProductService;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Services\MediaService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommerceController extends Controller implements HasMiddleware
{
    public function __construct(protected WorkspaceResolver $workspaces, protected ProductService $products, protected OrderWorkflowService $orders) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:commerce.view', only: ['index', 'show', 'categories', 'brands', 'audiences', 'orders', 'order', 'conversationProducts']), new Middleware('permission:commerce.manage', except: ['index', 'show', 'categories', 'brands', 'audiences', 'orders', 'order', 'conversationProducts'])];
    }

    public function index(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('commerce::user.index', [
            'products' => Product::query()
                ->with(['category', 'primaryMedia'])
                ->withCount('variants')
                ->withMin('variants as starting_price', 'price')
                ->withSum('variants as stock_total', 'stock_quantity')
                ->where('workspace_id', $workspace->id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request);
    }

    public function store(ProductDetailsRequest $request): RedirectResponse
    {
        $product = $this->products->createDraft($this->workspaces->current($request->user())->id, $request->validated());

        return redirect()->route('user.commerce.products.edit', ['product' => $product, 'step' => 2])->with('success', __('Draft created. Add product media next.'));
    }

    public function edit(Request $request, Product $product): View
    {
        $this->assertWorkspace($request, $product->workspace_id);

        return $this->form($request, $product);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->assertWorkspace($request, $product->workspace_id);
        $this->products->update($product, $request->validated());

        return back()->with('success', __('Product updated.'));
    }

    public function updateDetails(ProductDetailsRequest $request, Product $product): RedirectResponse
    {
        $this->assertWorkspace($request, $product->workspace_id);
        $this->products->updateDetails($product, $request->validated());

        return redirect()->route('user.commerce.products.edit', ['product' => $product, 'step' => 2])->with('success', __('Product details saved.'));
    }

    public function updateGallery(ProductGalleryRequest $request, Product $product): RedirectResponse
    {
        $this->assertWorkspace($request, $product->workspace_id);
        $this->products->updateGallery($product, $request->validated('media'));

        return redirect()->route('user.commerce.products.edit', ['product' => $product, 'step' => 3])->with('success', __('Gallery saved.'));
    }

    public function updateOptions(ProductOptionsRequest $request, Product $product): RedirectResponse
    {
        $this->assertWorkspace($request, $product->workspace_id);
        $this->products->updateOptions($product, $request->validated('options'));

        return redirect()->route('user.commerce.products.edit', ['product' => $product, 'step' => 4])->with('success', __('Options saved and variants generated.'));
    }

    public function previewVariants(Request $request, Product $product): JsonResponse
    {
        $this->assertWorkspace($request, $product->workspace_id);

        return response()->json(['variants' => $this->products->variantPreview($product)]);
    }

    public function updateVariants(ProductVariantsRequest $request, Product $product): RedirectResponse
    {
        $this->assertWorkspace($request, $product->workspace_id);
        $this->products->updateVariants($product, $request->validated('variants'));

        return redirect()->route('user.commerce.products.edit', ['product' => $product, 'step' => 5])->with('success', __('Variants and inventory saved.'));
    }

    public function publish(PublishProductRequest $request, Product $product): RedirectResponse
    {
        $this->assertWorkspace($request, $product->workspace_id);
        $this->products->publish($product, $request->string('status')->toString());

        return back()->with('success', __('Product status updated.'));
    }

    public function uploadMedia(UploadCommerceMediaRequest $request, MediaService $media): JsonResponse
    {
        $record = $media->upload($request->file('file'));

        return response()->json(['success' => true, 'data' => ['id' => $record->id, 'name' => $record->name, 'url' => $record->url, 'thumbnail_url' => $record->thumbnail_url, 'type' => $record->type, 'mime_type' => $record->mime_type, 'human_size' => $record->human_size]], 201);
    }

    public function show(Product $product): RedirectResponse
    {
        return redirect()->route('user.commerce.products.edit', $product);
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->assertWorkspace($request, $product->workspace_id);
        $product->delete();

        return back()->with('success', __('Product deleted.'));
    }

    public function bulkDestroyProducts(BulkDeleteCommerceRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $products = $this->bulkRecords(Product::class, $workspace->id, $request->validated('ids'));

        Product::query()->whereKey($products->modelKeys())->delete();

        return back()->with('success', trans_choice(':count product deleted.|:count products deleted.', $products->count(), ['count' => $products->count()]));
    }

    public function storeCategory(CategoryRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        Category::query()->create(['workspace_id' => $workspace->id, 'parent_id' => $request->integer('parent_id') ?: null, 'name' => $request->string('name'), 'slug' => Str::slug($request->string('name')), 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', __('Category created.'));
    }

    public function categories(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('commerce::user.categories', [
            'categories' => Category::query()
                ->with('parent')
                ->withCount(['products', 'children'])
                ->where('workspace_id', $workspace->id)
                ->orderByRaw('parent_id is not null')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateCategory(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->assertWorkspace($request, $category->workspace_id);
        $category->update([
            'parent_id' => $request->integer('parent_id') ?: null,
            'name' => $request->string('name')->toString(),
            'slug' => Str::slug($request->string('name')->toString()),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('Category updated.'));
    }

    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        $this->assertWorkspace($request, $category->workspace_id);
        if ($category->products()->exists() || $category->children()->exists()) {
            throw ValidationException::withMessages(['category' => __('Move its products and child categories before deleting this category.')]);
        }
        $category->delete();

        return back()->with('success', __('Category deleted.'));
    }

    public function bulkDestroyCategories(BulkDeleteCommerceRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $categories = $this->bulkRecords(Category::class, $workspace->id, $request->validated('ids'))->loadCount(['products', 'children']);

        if ($categories->contains(fn (Category $category): bool => $category->products_count > 0 || $category->children_count > 0)) {
            throw ValidationException::withMessages(['ids' => __('Move products and child categories before deleting the selected categories.')]);
        }

        Category::query()->whereKey($categories->modelKeys())->delete();

        return back()->with('success', trans_choice(':count category deleted.|:count categories deleted.', $categories->count(), ['count' => $categories->count()]));
    }

    public function brands(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('commerce::user.taxonomies', [
            'records' => Brand::query()->withCount('products')->where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'title' => __('Brands'),
            'singular' => __('brand'),
            'description' => __('Create the apparel brands buyers can select on products.'),
            'icon' => 'ph-seal-check',
            'storeRoute' => route('user.commerce.brands.store'),
            'updateRouteName' => 'user.commerce.brands.update',
            'destroyRouteName' => 'user.commerce.brands.destroy',
            'bulkDestroyRoute' => route('user.commerce.brands.bulk-destroy'),
            'routeParameter' => 'brand',
            'maxLength' => 120,
            'helpKey' => 'brands',
        ]);
    }

    public function storeBrand(BrandRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $name = $request->string('name')->toString();
        Brand::query()->create(['workspace_id' => $workspace->id, 'name' => $name, 'slug' => $this->taxonomySlug(Brand::class, $workspace->id, $name), 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', __('Brand created.'));
    }

    public function updateBrand(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->assertWorkspace($request, $brand->workspace_id);
        $name = $request->string('name')->toString();
        $brand->update(['name' => $name, 'slug' => $this->taxonomySlug(Brand::class, $brand->workspace_id, $name, $brand->id), 'is_active' => $request->boolean('is_active')]);
        Product::query()->where('workspace_id', $brand->workspace_id)->where('brand_id', $brand->id)->update(['brand' => $name]);

        return back()->with('success', __('Brand updated.'));
    }

    public function destroyBrand(Request $request, Brand $brand): RedirectResponse
    {
        $this->assertWorkspace($request, $brand->workspace_id);
        if ($brand->products()->exists()) {
            throw ValidationException::withMessages(['brand' => __('Move its products before deleting this brand.')]);
        }
        $brand->delete();

        return back()->with('success', __('Brand deleted.'));
    }

    public function bulkDestroyBrands(BulkDeleteCommerceRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $brands = $this->bulkRecords(Brand::class, $workspace->id, $request->validated('ids'))->loadCount('products');

        if ($brands->contains(fn (Brand $brand): bool => $brand->products_count > 0)) {
            throw ValidationException::withMessages(['ids' => __('Move products before deleting the selected brands.')]);
        }

        Brand::query()->whereKey($brands->modelKeys())->delete();

        return back()->with('success', trans_choice(':count brand deleted.|:count brands deleted.', $brands->count(), ['count' => $brands->count()]));
    }

    public function audiences(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('commerce::user.taxonomies', [
            'records' => Audience::query()->withCount('products')->where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'title' => __('Audiences'),
            'singular' => __('audience'),
            'description' => __('Create buyer groups such as Women, Men, Kids, Baby, or Unisex.'),
            'icon' => 'ph-users-three',
            'storeRoute' => route('user.commerce.audiences.store'),
            'updateRouteName' => 'user.commerce.audiences.update',
            'destroyRouteName' => 'user.commerce.audiences.destroy',
            'bulkDestroyRoute' => route('user.commerce.audiences.bulk-destroy'),
            'routeParameter' => 'audience',
            'maxLength' => 80,
            'helpKey' => 'audiences',
        ]);
    }

    public function storeAudience(AudienceRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $name = $request->string('name')->toString();
        Audience::query()->create(['workspace_id' => $workspace->id, 'name' => $name, 'slug' => $this->taxonomySlug(Audience::class, $workspace->id, $name), 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', __('Audience created.'));
    }

    public function updateAudience(AudienceRequest $request, Audience $audience): RedirectResponse
    {
        $this->assertWorkspace($request, $audience->workspace_id);
        $name = $request->string('name')->toString();
        $audience->update(['name' => $name, 'slug' => $this->taxonomySlug(Audience::class, $audience->workspace_id, $name, $audience->id), 'is_active' => $request->boolean('is_active')]);
        Product::query()->where('workspace_id', $audience->workspace_id)->where('audience_id', $audience->id)->update(['audience' => $name]);

        return back()->with('success', __('Audience updated.'));
    }

    public function destroyAudience(Request $request, Audience $audience): RedirectResponse
    {
        $this->assertWorkspace($request, $audience->workspace_id);
        if ($audience->products()->exists()) {
            throw ValidationException::withMessages(['audience' => __('Move its products before deleting this audience.')]);
        }
        $audience->delete();

        return back()->with('success', __('Audience deleted.'));
    }

    public function bulkDestroyAudiences(BulkDeleteCommerceRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $audiences = $this->bulkRecords(Audience::class, $workspace->id, $request->validated('ids'))->loadCount('products');

        if ($audiences->contains(fn (Audience $audience): bool => $audience->products_count > 0)) {
            throw ValidationException::withMessages(['ids' => __('Move products before deleting the selected audiences.')]);
        }

        Audience::query()->whereKey($audiences->modelKeys())->delete();

        return back()->with('success', trans_choice(':count audience deleted.|:count audiences deleted.', $audiences->count(), ['count' => $audiences->count()]));
    }

    public function catalog(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());
        $catalogs = Catalog::query()->with(['channelAccount', 'itemSyncs', 'syncRuns' => fn ($query) => $query->latest()->limit(5)])->where('workspace_id', $workspace->id)->get();
        $diagnostics = $catalogs->mapWithKeys(fn (Catalog $catalog): array => [$catalog->id => app(CatalogDiagnosticsService::class)->diagnose($catalog)])->all();

        return view('commerce::user.catalog', ['catalogs' => $catalogs, 'diagnostics' => $diagnostics, 'channels' => ChannelAccount::query()->where('workspace_id', $workspace->id)->where('provider', 'whatsapp')->get()]);
    }

    public function storeCatalog(CatalogRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $channel = ChannelAccount::query()->where('workspace_id', $workspace->id)->where('provider', 'whatsapp')->findOrFail($request->integer('channel_account_id'));
        Catalog::query()->updateOrCreate(['workspace_id' => $workspace->id, 'channel_account_id' => $channel->id], ['meta_catalog_id' => $request->string('meta_catalog_id')->toString(), 'sync_mode' => $request->string('sync_mode')->toString(), 'readiness_state' => 'checking', 'feed_token' => Catalog::query()->where('channel_account_id', $channel->id)->value('feed_token') ?: Str::random(64), 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', __('Catalog connection saved.'));
    }

    public function rotateFeedToken(Request $request, Catalog $catalog): RedirectResponse
    {
        $this->assertWorkspace($request, $catalog->workspace_id);
        $catalog->update(['feed_token' => Str::random(64)]);

        return back()->with('success', __('Feed token rotated. Update the scheduled source in Meta Commerce Manager.'));
    }

    public function syncCatalog(Request $request, Catalog $catalog, CatalogSyncService $sync): RedirectResponse
    {
        $this->assertWorkspace($request, $catalog->workspace_id);
        $sync->queue($catalog);

        return back()->with('success', __('Catalog synchronization queued.'));
    }

    public function updateCommerceSettings(CommerceSettingsRequest $request, Catalog $catalog, MetaCatalogClient $meta): RedirectResponse
    {
        $this->assertWorkspace($request, $catalog->workspace_id);
        $validated = $request->validated();
        $catalog->loadMissing('channelAccount');
        $response = $meta->updateCommerceSettings((string) $catalog->channelAccount->provider_phone_id, (string) $catalog->channelAccount->credential('access_token'), (bool) $validated['cart_enabled'], (bool) $validated['catalog_visible']);
        if (! $response->successful()) {
            throw ValidationException::withMessages(['catalog' => $response->json('error.message') ?: 'Meta rejected the WhatsApp commerce settings.']);
        }
        $catalog->update(['cart_enabled' => $validated['cart_enabled'], 'catalog_visible' => $validated['catalog_visible'], 'readiness_state' => 'ready']);

        return back()->with('success', __('WhatsApp catalog and cart settings updated.'));
    }

    public function orders(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('commerce::user.orders', ['orders' => Order::query()->with('contact')->where('workspace_id', $workspace->id)->latest()->paginate(25)]);
    }

    public function order(Request $request, Order $order): View
    {
        $this->assertWorkspace($request, $order->workspace_id);

        return view('commerce::user.order', ['order' => $order->load(['items', 'contact', 'conversation'])]);
    }

    public function quote(QuoteOrderRequest $request, Order $order): RedirectResponse
    {
        $this->assertWorkspace($request, $order->workspace_id);
        $this->orders->quote($order, $request->validated());

        return back()->with('success', __('Quote saved.'));
    }

    public function transition(TransitionOrderRequest $request, Order $order): RedirectResponse
    {
        $this->assertWorkspace($request, $order->workspace_id);
        $this->orders->transition($order, $request->string('status')->toString(), $request->validated());

        return back()->with('success', __('Order status updated.'));
    }

    public function sendCatalog(Request $request, Conversation $conversation, CatalogMessageService $messages): JsonResponse
    {
        $this->assertWorkspace($request, $conversation->workspace_id);
        $message = $messages->send($conversation->load(['channelAccount', 'contact']));

        return response()->json(['ok' => true, 'message' => ['id' => $message->id, 'body' => $message->body, 'direction' => 'outbound', 'type' => 'interactive', 'status' => $message->status]], 201);
    }

    public function conversationProducts(Request $request, Conversation $conversation): JsonResponse
    {
        $this->assertWorkspace($request, $conversation->workspace_id);
        $products = Product::query()->with(['primaryMedia', 'gallery.media', 'variants' => fn ($query) => $query->whereIn('status', ['active', 'out_of_stock'])])->where('workspace_id', $conversation->workspace_id)->where('status', 'active')->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->toString().'%'))->orderBy('name')->limit(50)->get();

        return response()->json([
            'session_active' => $conversation->session_expires_at?->isFuture() ?? false,
            'session_expires_at' => $conversation->session_expires_at?->toIso8601String(),
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $product->primaryMedia?->url,
                'videos' => $product->gallery->where('media_type', 'video')->map(fn ($item): array => ['id' => $item->id, 'url' => $item->media?->url])->values()->all(),
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => ['id' => $variant->id, 'label' => collect($variant->attributes)->implode(' / '), 'price' => (float) $variant->price, 'stock' => $variant->stock_quantity, 'status' => $variant->status, 'retailer_id' => $variant->meta_retailer_id])->values()->all(),
            ])->values(),
        ]);
    }

    public function sendProduct(SendProductMessageRequest $request, Conversation $conversation, CatalogMessageService $messages): JsonResponse
    {
        $this->assertWorkspace($request, $conversation->workspace_id);
        $message = $messages->sendProduct($conversation->load(['channelAccount', 'contact']), $request->integer('variant_id'), $request->string('body')->toString() ?: null);

        return response()->json(['ok' => true, 'message' => $this->messagePayload($message)], 201);
    }

    public function sendProductList(SendProductListRequest $request, Conversation $conversation, CatalogMessageService $messages): JsonResponse
    {
        $this->assertWorkspace($request, $conversation->workspace_id);
        $message = $messages->sendProductList($conversation->load(['channelAccount', 'contact']), $request->validated('variant_ids'), $request->string('header')->toString() ?: null, $request->string('body')->toString() ?: null);

        return response()->json(['ok' => true, 'message' => $this->messagePayload($message)], 201);
    }

    public function sendProductVideo(SendProductVideoRequest $request, Conversation $conversation, CatalogMessageService $messages): JsonResponse
    {
        $this->assertWorkspace($request, $conversation->workspace_id);
        $message = $messages->sendVideo($conversation->load(['channelAccount', 'contact']), $request->integer('product_media_id'), $request->string('caption')->toString() ?: null);

        return response()->json(['ok' => true, 'message' => $this->messagePayload($message)], 201);
    }

    protected function form(Request $request, ?Product $product = null): View
    {
        $workspace = $this->workspaces->current($request->user());

        $product?->load(['gallery.media', 'options.values', 'variants.media']);

        return view('commerce::user.form', [
            'product' => $product,
            'step' => $product ? max(1, min(5, $request->integer('step', $product->wizard_step))) : 1,
            'categories' => Category::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'brands' => Brand::query()->where('workspace_id', $workspace->id)->where('is_active', true)->orderBy('name')->get(),
            'audiences' => Audience::query()->where('workspace_id', $workspace->id)->where('is_active', true)->orderBy('name')->get(),
            'variantPreview' => $product ? $this->products->variantPreview($product) : [],
            'readinessIssues' => $product ? app(ProductReadinessService::class)->issues($product) : [],
        ]);
    }

    protected function assertWorkspace(Request $request, int $workspaceId): void
    {
        abort_unless($this->workspaces->current($request->user())?->id === $workspaceId, 404);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, int|string>  $ids
     * @return EloquentCollection<int, Model>
     */
    protected function bulkRecords(string $model, int $workspaceId, array $ids): EloquentCollection
    {
        $uniqueIds = collect($ids)->map(fn (int|string $id): int => (int) $id)->unique()->values();
        $records = $model::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $uniqueIds)
            ->get();

        abort_unless($records->count() === $uniqueIds->count(), 404);

        return $records;
    }

    /**
     * @param  class-string<Brand|Audience>  $model
     */
    protected function taxonomySlug(string $model, int $workspaceId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $suffix = 2;
        while ($model::query()->where('workspace_id', $workspaceId)->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function messagePayload(Message $message): array
    {
        return ['id' => $message->id, 'body' => $message->body, 'direction' => 'outbound', 'type' => $message->type, 'status' => $message->status];
    }
}
