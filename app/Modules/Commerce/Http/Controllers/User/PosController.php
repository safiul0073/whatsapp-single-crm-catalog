<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\AddressSuggestionRequest;
use App\Modules\Commerce\Http\Requests\PosCheckoutRequest;
use App\Modules\Commerce\Http\Requests\PosContactAddressRequest;
use App\Modules\Commerce\Http\Requests\PosCustomerRequest;
use App\Modules\Commerce\Http\Requests\PostalLookupRequest;
use App\Modules\Commerce\Http\Requests\RecordOrderPaymentRequest;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\ContactAddress;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\PosHeldSale;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\AddressAutocompleteService;
use App\Modules\Commerce\Services\OrderDocumentPresenter;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Services\PosService;
use App\Modules\Commerce\Services\PostalCodeLookupService;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces, protected PosService $pos) {}

    public function index(Request $request): View
    {
        $workspace = $this->workspaces->current($request->user());

        return view('commerce::user.pos', [
            'settings' => StoreOrderSetting::forWorkspace($workspace->id),
            'categories' => Category::query()->where('workspace_id', $workspace->id)->where('is_active', true)->whereHas('products', fn ($query) => $query->where('status', 'active'))->orderBy('name')->get(['id', 'name']),
            'canDiscount' => $request->user()->can('commerce.orders.update'),
            'paymentMethods' => $this->pos->paymentMethods($workspace->id),
            'wholesaleEnabled' => StoreFeatures::wholesaleEnabled($workspace->id),
            'retailEnabled' => StoreFeatures::retailEnabled($workspace->id),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 150);
        $code = mb_substr(trim((string) $request->query('code', '')), 0, 150);
        $categoryId = $request->integer('category');
        $products = Product::query()->where('workspace_id', $workspace->id)->where('status', 'active')->where('visibility', 'published')
            // Barcode scan: exact SKU / retailer id match on a variant or the product.
            ->when($code !== '', fn ($query) => $query->where(fn ($query) => $query->where('sku', $code)->orWhereHas('variants', fn ($query) => $query->where('status', 'active')->where(fn ($query) => $query->where('sku', $code)->orWhere('meta_retailer_id', $code)))))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%')->orWhereHas('variants', fn ($query) => $query->where('sku', 'like', '%'.$search.'%'))))
            ->with(['primaryMedia', 'gallery.media', 'variants' => fn ($query) => $query->where('status', 'active'), 'colors.swatchMedia', 'options.values'])->orderBy('name')->limit(30)->get();
        $held = OrderReservation::query()->whereIn('variant_id', $products->flatMap->variants->pluck('id'))->where('state', 'reserved')->where('expires_at', '>', now())->get()->groupBy('variant_id')->map->sum('quantity');

        $matchedVariant = $code !== '' ? $products->flatMap->variants->first(fn ($variant): bool => $variant->sku === $code || $variant->meta_retailer_id === $code) : null;

        $retailEnabled = StoreFeatures::retailEnabled($workspace->id);

        return response()->json(['matched_variant_id' => $matchedVariant?->id, 'data' => $products->map(function (Product $product) use ($held, $retailEnabled): array {
            $variants = $product->variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->sku.' · '.($variant->size ?: ($variant->attributes['size'] ?? __('One size'))),
                'color_id' => $variant->color_id,
                'size' => $variant->size ?: ($variant->attributes['size'] ?? __('One size')),
                'price' => $variant->price,
                'available' => max(0, $variant->stock_quantity - ($held[$variant->id] ?? 0)),
            ])->values();
            $sizesFor = fn ($colorId) => $variants->filter(fn (array $variant): bool => $variant['color_id'] === $colorId)
                ->filter(fn (array $variant): bool => $variant['available'] > 0)
                ->map(fn (array $variant): array => ['name' => $variant['size'], 'available' => $variant['available']])
                ->values();
            $primaryImage = $product->primaryMedia?->isImage() ? $product->primaryMedia->url : null;
            $gallery = $product->gallery->filter(fn ($image): bool => $image->media?->isImage() && $image->media_type === 'image')->map(fn ($image): array => [
                'url' => $image->media->url,
                'color_id' => $image->color_id,
                'alt' => $image->alt_text ?: $product->name,
            ])->values();
            if ($primaryImage && ! $gallery->contains('url', $primaryImage)) {
                $gallery->prepend(['url' => $primaryImage, 'color_id' => null, 'alt' => $product->name]);
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'single_piece_price' => $product->single_piece_price,
                'wholesale_price' => $product->wholesale_price,
                'mode' => $retailEnabled ? $product->selling_mode : 'wholesale',
                'wholesale_enabled' => $product->isWholesaleEnabled(),
                'image_url' => $primaryImage,
                'gallery' => $gallery->values(),
                'variants' => $variants,
                'colors' => $product->colors->map(fn ($color): array => [
                    'id' => $color->id,
                    'name' => $color->display_name,
                    'hex_code' => $color->hex_code,
                    'available' => $sizesFor($color->id)->sum('available'),
                    'sizes' => $sizesFor($color->id),
                    'swatch_image_url' => $color->swatchMedia?->isImage() ? $color->swatchMedia->url : null,
                ])->values(),
                'sizes' => $sizesFor(null),
                'available' => $variants->sum('available'),
                'ratios' => $product->getEffectiveSizeRatios(),
                'multiplier' => max(1, (int) $product->ws_ratio_multiplier),
                'minimum' => $product->ws_main_moq,
                'color_minimum' => max(1, (int) $product->ws_color_moq),
                'minimum_sizes' => max(1, (int) ($product->ws_min_sizes ?? 1)),
                'category_id' => $product->category_id,
                'pack_pricing' => $product->usesPackPricing(),
                'pack_prices' => $product->usesPackPricing() ? $product->packPrices() : [],
                'min_packs' => $product->usesPackPricing() ? $product->minimumPacks() : null,
            ];
        })->values()]);
    }

    public function preview(PosCheckoutRequest $request, UnifiedOrderService $orders): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $data = $this->withDiscounts($request, $workspace, $request->validated(), $orders);
        $data['source'] = 'pos';

        return response()->json(['data' => $orders->preview($workspace, $data)]);
    }

    public function checkout(PosCheckoutRequest $request, UnifiedOrderService $orders): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $data = $this->withDiscounts($request, $workspace, $request->validated(), $orders);
        $order = $this->pos->checkout($workspace, $data, $request->user());

        return response()->json(['data' => ['id' => $order->id, 'url' => route('user.commerce.orders.show', $order), 'receipt_url' => route('user.commerce.pos.receipt', $order)]], 201);
    }

    public function payment(RecordOrderPaymentRequest $request, Order $order): RedirectResponse
    {
        $this->assertOrder($request, $order);
        $this->pos->recordPayment($order, $request->validated(), $request->user());

        return back()->with('success', __('Payment recorded.'));
    }

    public function pickup(Request $request, Order $order): RedirectResponse
    {
        $this->assertOrder($request, $order);
        $this->pos->pickup($order);

        return back()->with('success', __('Pickup completed.'));
    }

    public function receipt(Request $request, Order $order, OrderDocumentPresenter $documents): View
    {
        $this->assertOrder($request, $order);
        $order->ensureInvoice();

        return view('commerce::user.pos-receipt', $documents->present($documents->load($order)));
    }

    public function customerBalance(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($contact->workspace_id === $this->workspaces->current($request->user())->id, 404);

        return response()->json(['data' => $this->pos->customerBalances($contact)]);
    }

    private function assertOrder(Request $request, Order $order): void
    {
        abort_unless($order->workspace_id === $this->workspaces->current($request->user())->id && $order->source === 'pos', 404);
    }

    public function heldSales(Request $request): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());

        return response()->json(['data' => PosHeldSale::query()->where('workspace_id', $workspace->id)->latest()->limit(50)->get(['id', 'label', 'payload', 'created_at'])]);
    }

    public function holdSale(Request $request): JsonResponse
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:120'], 'payload' => ['required', 'array'], 'payload.lines' => ['required', 'array', 'min:1', 'max:100']]);
        $workspace = $this->workspaces->current($request->user());
        $held = PosHeldSale::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'label' => filled($data['label'] ?? null) ? $data['label'] : __('Sale held at :time', ['time' => now()->format('H:i')]),
            'payload' => $data['payload'],
        ]);

        return response()->json(['data' => $held->only(['id', 'label', 'payload', 'created_at'])], 201);
    }

    public function releaseHeldSale(Request $request, int $held): JsonResponse
    {
        PosHeldSale::query()->where('workspace_id', $this->workspaces->current($request->user())->id)->findOrFail($held)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function customers(Request $request): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $contacts = Contact::query()->where('workspace_id', $workspace->id)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('phone', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'phone', 'email']);
        $addresses = ContactAddress::query()->where('workspace_id', $workspace->id)->whereIn('contact_id', $contacts->pluck('id'))->orderByDesc('is_default')->latest('id')->get()->groupBy('contact_id');

        return response()->json(['data' => $contacts->map(fn (Contact $contact): array => $this->customerPayload($contact, $addresses->get($contact->id, new Collection)))]);
    }

    public function storeCustomer(PosCustomerRequest $request): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $data = $request->validated();
        $email = filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null;
        $contact = Contact::query()->where('workspace_id', $workspace->id)->where('phone', $data['phone'])->first();
        $contact ??= Contact::query()->create([
            'workspace_id' => $workspace->id,
            'phone' => $data['phone'],
            'name' => $data['name'],
            'email' => $email !== null && ! Contact::query()->where('workspace_id', $workspace->id)->where('email', $email)->exists() ? $email : null,
            'country' => $data['address']['country'] ?? 'US',
        ]);
        if (filled($data['address']['line1'] ?? null)) {
            $this->saveAddress($contact, $data['address']);
        }

        return response()->json(['data' => $this->customerPayload($contact, ContactAddress::query()->where('workspace_id', $workspace->id)->where('contact_id', $contact->id)->orderByDesc('is_default')->latest('id')->get())], 201);
    }

    public function storeAddress(PosContactAddressRequest $request, Contact $contact): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());
        abort_unless($contact->workspace_id === $workspace->id, 404);
        $address = $this->saveAddress($contact, $request->validated());

        return response()->json(['data' => $this->addressPayload($address)], 201);
    }

    public function addressSuggestions(AddressSuggestionRequest $request, AddressAutocompleteService $addresses): JsonResponse
    {
        return response()->json(['data' => $addresses->suggest($request->validated('q'), $request->validated('country'))]);
    }

    public function postalLookup(PostalLookupRequest $request, PostalCodeLookupService $postal): JsonResponse
    {
        return response()->json(['data' => $postal->lookup($request->validated('country'), $request->validated('postal_code'))]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveAddress(Contact $contact, array $data): ContactAddress
    {
        $attributes = collect($data)->only(['name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country'])->all();
        $address = ContactAddress::query()->firstOrNew(['workspace_id' => $contact->workspace_id, 'contact_id' => $contact->id, 'line1' => $attributes['line1'], 'postal_code' => $attributes['postal_code'] ?? null]);
        $address->fill([...$attributes, 'country' => $attributes['country'] ?? 'US']);
        if (! $address->exists) {
            $address->is_default = ! ContactAddress::query()->where('contact_id', $contact->id)->exists();
        }
        $address->save();

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(Contact $contact, Collection $addresses): array
    {
        return [...$contact->only(['id', 'name', 'phone', 'email']), 'addresses' => $addresses->take(5)->map(fn (ContactAddress $address): array => $this->addressPayload($address))->values()->all()];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(ContactAddress $address): array
    {
        return $address->only(['id', 'name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country', 'is_default']);
    }

    /**
     * Turns cashier line and order discounts into one order adjustment, computed from the
     * server-side subtotal so the browser cannot discount below cost by tampering with prices.
     */
    private function withDiscounts(Request $request, Workspace $workspace, array $data, UnifiedOrderService $orders): array
    {
        $lineDiscounts = collect($data['groups'])->sum(fn (array $group): float => (float) ($group['line_discount'] ?? 0));
        $orderDiscount = $data['discount'] ?? null;
        foreach ($data['groups'] as $index => $group) {
            unset($data['groups'][$index]['line_discount']);
        }
        unset($data['discount']);
        if ($lineDiscounts <= 0 && (float) ($orderDiscount['value'] ?? 0) <= 0) {
            return $data;
        }
        if (! $request->user()->can('commerce.orders.update')) {
            throw ValidationException::withMessages(['discount' => 'You are not allowed to give discounts.']);
        }
        $quote = $orders->preview($workspace, [...$data, 'source' => 'pos']);
        $precision = (int) $quote['precision'];
        $subtotal = OrderMoney::minor($quote['subtotal'], $precision);
        $lines = OrderMoney::minor(number_format($lineDiscounts, $precision, '.', ''), $precision);
        $rest = max(0, $subtotal - $lines);
        $order = match ($orderDiscount['type'] ?? null) {
            'percent' => intdiv($rest * (int) round(min(100, (float) $orderDiscount['value']) * 100), 10000),
            'amount' => OrderMoney::minor(number_format((float) $orderDiscount['value'], $precision, '.', ''), $precision),
            default => 0,
        };
        $total = $lines + $order;
        if ($total > $subtotal) {
            throw ValidationException::withMessages(['discount' => 'Discounts cannot exceed the subtotal.']);
        }
        $data['adjustments'] = [['type' => 'pos_discount', 'amount' => OrderMoney::decimal($total, $precision), 'currency' => $quote['currency'], 'reference' => 'POS discount']];

        return $data;
    }
}
