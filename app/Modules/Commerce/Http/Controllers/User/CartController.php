<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\OwnerCartItemRequest;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Models\Cart;
use App\Modules\Commerce\Storefront\Models\CartItem;
use App\Modules\Commerce\Storefront\Services\CartService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Lets the store owner see open storefront carts (who added what) and edit them on the shopper's behalf.
 */
class CartController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces, protected CartService $carts) {}

    public function index(Request $request): View
    {
        $workspaceId = $this->workspaceId($request);
        $who = in_array($request->query('who'), ['customers', 'guests'], true) ? $request->query('who') : 'all';
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);

        $open = $this->openCarts($workspaceId);
        $stats = [
            'open' => (clone $open)->count(),
            'customers' => (clone $open)->whereNotNull('customer_id')->count(),
            'pieces' => (int) CartItem::query()->whereIn('cart_id', (clone $open)->select('id'))->sum('quantity'),
        ];

        $carts = $open
            ->with('customer.contact')
            ->withCount('items')
            ->withSum('items as pieces', 'quantity')
            ->withSum('items as value', DB::raw('quantity * price_snapshot'))
            ->withMax('items as last_activity', 'updated_at')
            ->when($who === 'customers', fn (Builder $q) => $q->whereNotNull('customer_id'))
            ->when($who === 'guests', fn (Builder $q) => $q->whereNull('customer_id'))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $q->whereHas('customer', fn (Builder $c) => $c->where('verified_phone', 'like', $like)
                    ->orWhereHas('contact', fn (Builder $contact) => $contact->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->orderByDesc('last_activity')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $settings = StoreOrderSetting::forWorkspace($workspaceId);

        return view('commerce::user.carts.index', [
            'carts' => $carts,
            'stats' => $stats,
            'who' => $who,
            'search' => $search,
            'currency' => $settings->currency,
            'precision' => $settings->precision(),
        ]);
    }

    public function show(Request $request, Cart $cart): View
    {
        $this->assertOpen($request, $cart);
        $cart->load('customer.contact');

        return view('commerce::user.carts.show', [
            'cart' => $cart,
            'summary' => $this->carts->summary($cart),
            'lastActivity' => $cart->items()->max('updated_at'),
            'retailEnabled' => StoreFeatures::retailEnabled($cart->workspace_id),
        ]);
    }

    /**
     * Retail-sellable products with their active sizes and the stock still free to add.
     */
    public function products(Request $request): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        if (! StoreFeatures::retailEnabled($workspaceId)) {
            return response()->json(['data' => []]);
        }
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 150);
        $like = '%'.addcslashes($search, '\\%_').'%';
        $products = Product::query()->where('workspace_id', $workspaceId)->where('status', 'active')->where('visibility', 'published')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like))))
            ->with(['primaryMedia', 'variants' => fn ($q) => $q->where('status', 'active')->with('color'), 'variants.media'])
            ->orderBy('name')->limit(30)->get()
            ->filter(fn (Product $product): bool => $product->isRetailEnabled() && $product->variants->isNotEmpty())
            ->values();
        $reserved = ProductVariant::reservedQuantities($products->flatMap->variants->pluck('id'));

        return response()->json(['data' => $products->map(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'image_url' => $product->primaryMedia?->isImage() ? $product->primaryMedia->url : null,
            'price' => $product->single_piece_price,
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'label' => collect([$variant->color?->display_name ?? $variant->color?->name, $variant->size ?: ($variant->attributes['size'] ?? null), $variant->sku])->filter()->implode(' · '),
                'price' => $product->single_piece_price ?? $variant->price,
                'available' => $variant->availableQuantity($reserved[$variant->id] ?? 0),
            ])->values(),
        ])]);
    }

    public function addItem(OwnerCartItemRequest $request, Cart $cart): RedirectResponse
    {
        return $this->mutate($request, $cart, fn (Cart $locked) => $this->carts->addRetail($locked, $request->integer('variant_id'), $request->integer('quantity')), __('Product added to the cart.'));
    }

    public function updateItem(OwnerCartItemRequest $request, Cart $cart, int $item): RedirectResponse
    {
        return $this->mutate($request, $cart, fn (Cart $locked) => $this->carts->setRetailQuantity($locked, $item, $request->integer('quantity')), __('Quantity updated.'));
    }

    public function destroyItem(Request $request, Cart $cart, int $item): RedirectResponse
    {
        return $this->mutate($request, $cart, fn (Cart $locked) => $this->carts->removeLine($locked, $item), __('Item removed from the cart.'));
    }

    public function destroyGroup(Request $request, Cart $cart, int $group): RedirectResponse
    {
        return $this->mutate($request, $cart, fn (Cart $locked) => $this->carts->removeGroup($locked, $group), __('Pack removed from the cart.'));
    }

    public function clear(Request $request, Cart $cart): RedirectResponse
    {
        return $this->mutate($request, $cart, fn (Cart $locked) => $this->carts->clear($locked), __('Cart cleared.'));
    }

    /**
     * Runs a cart change under the same lock the storefront uses and turns stock or rule errors into form errors.
     *
     * @param  Closure(Cart): mixed  $change
     */
    private function mutate(Request $request, Cart $cart, Closure $change, string $message): RedirectResponse
    {
        $this->assertOpen($request, $cart);
        try {
            DB::transaction(function () use ($cart, $change): void {
                $locked = Cart::query()->whereKey($cart->id)->whereNull('completed_at')->lockForUpdate()->firstOrFail();
                $change($locked);
            });
        } catch (HttpResponseException $e) {
            return back()->withErrors(['cart' => (string) ($e->getResponse()->getData(true)['message'] ?? __('This change could not be saved.'))]);
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 422) {
                throw $e;
            }

            return back()->withErrors(['cart' => $e->getMessage() ?: __('This change could not be saved.')]);
        }

        return redirect()->route('user.commerce.carts.show', $cart)->with('success', $message);
    }

    private function assertOpen(Request $request, Cart $cart): void
    {
        abort_unless($cart->workspace_id === $this->workspaceId($request), 404);
        abort_if($cart->completed_at !== null || $cart->expires_at->isPast(), 404);
    }

    /**
     * @return Builder<Cart>
     */
    private function openCarts(int $workspaceId): Builder
    {
        return Cart::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('completed_at')
            ->where('expires_at', '>', now())
            ->whereHas('items');
    }

    private function workspaceId(Request $request): int
    {
        return $this->workspaces->current($request->user())->id;
    }
}
