<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\PosCheckoutRequest;
use App\Modules\Commerce\Http\Requests\RecordOrderPaymentRequest;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderReservation;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\PosService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
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
            'contacts' => Contact::query()->where('workspace_id', $workspace->id)->orderBy('name')->get(['id', 'name', 'phone']),
            'paymentMethods' => $this->pos->paymentMethods($workspace->id),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $workspace = $this->workspaces->current($request->user());
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 150);
        $products = Product::query()->where('workspace_id', $workspace->id)->where('status', 'active')->where('visibility', 'published')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%')->orWhereHas('variants', fn ($query) => $query->where('sku', 'like', '%'.$search.'%'))))
            ->with(['primaryMedia', 'gallery.media', 'variants' => fn ($query) => $query->where('status', 'active'), 'colors.swatchMedia', 'options.values'])->orderBy('name')->limit(30)->get();
        $held = OrderReservation::query()->whereIn('variant_id', $products->flatMap->variants->pluck('id'))->where('state', 'reserved')->where('expires_at', '>', now())->get()->groupBy('variant_id')->map->sum('quantity');

        return response()->json(['data' => $products->map(function (Product $product) use ($held): array {
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
                'mode' => $product->selling_mode,
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
            ];
        })->values()]);
    }

    public function preview(PosCheckoutRequest $request, UnifiedOrderService $orders): JsonResponse
    {
        $data = $request->validated();
        $this->validatePosMode($data['groups']);
        $data['source'] = 'pos';

        return response()->json(['data' => $orders->preview($this->workspaces->current($request->user()), $data)]);
    }

    public function checkout(PosCheckoutRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->validatePosMode($data['groups']);
        $order = $this->pos->checkout($this->workspaces->current($request->user()), $data, $request->user());

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

    public function receipt(Request $request, Order $order): View
    {
        $this->assertOrder($request, $order);

        return view('commerce::user.pos-receipt', ['order' => $order->load(['items', 'groups', 'payments.staff', 'contact']), 'workspace' => $this->workspaces->current($request->user())]);
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

    private function validatePosMode(array $groups): void
    {
        $modes = collect($groups)->pluck('mode')->unique();
        if ($modes->count() > 1) {
            throw ValidationException::withMessages(['groups' => 'Retail and wholesale selections must be placed in separate orders.']);
        }

        if ($modes->first() === 'wholesale' && collect($groups)->pluck('product_id')->unique()->count() > 1) {
            throw ValidationException::withMessages(['groups' => 'A wholesale order can contain packs for one product only.']);
        }
    }
}
