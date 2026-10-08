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
            ->with(['variants' => fn ($query) => $query->where('status', 'active'), 'colors', 'options.values'])->orderBy('name')->limit(30)->get();
        $held = OrderReservation::query()->whereIn('variant_id', $products->flatMap->variants->pluck('id'))->where('state', 'reserved')->where('expires_at', '>', now())->get()->groupBy('variant_id')->map->sum('quantity');

        return response()->json(['data' => $products->map(fn (Product $product): array => [
            'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'mode' => $product->selling_mode,
            'wholesale_enabled' => $product->isWholesaleEnabled(),
            'variants' => $product->variants->map(fn ($variant): array => ['id' => $variant->id, 'name' => $variant->sku.' · '.$variant->size, 'available' => max(0, $variant->stock_quantity - ($held[$variant->id] ?? 0))])->values(),
            'colors' => $product->colors->map(fn ($color): array => ['id' => $color->id, 'name' => $color->name])->values(),
            'ratios' => $product->getEffectiveSizeRatios(), 'multiplier' => max(1, (int) $product->ws_ratio_multiplier), 'minimum' => $product->ws_main_moq,
        ])->values()]);
    }

    public function preview(PosCheckoutRequest $request, UnifiedOrderService $orders): JsonResponse
    {
        $data = $request->validated();
        $data['source'] = 'pos';

        return response()->json(['data' => $orders->preview($this->workspaces->current($request->user()), $data)]);
    }

    public function checkout(PosCheckoutRequest $request): JsonResponse
    {
        $order = $this->pos->checkout($this->workspaces->current($request->user()), $request->validated(), $request->user());

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
}
