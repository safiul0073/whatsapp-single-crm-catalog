<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commerce\Http\Requests\InventoryAdjustmentRequest;
use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Services\InventoryService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces, protected InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $workspaceId = $this->workspaces->current($request->user())->id;
        $threshold = $this->inventory->threshold($workspaceId);
        $status = in_array($request->query('status'), ['low', 'out', 'in'], true) ? $request->query('status') : 'all';
        $search = trim((string) $request->query('q', ''));
        $categoryId = $request->integer('category_id') ?: null;

        $query = $this->inventory->variants($workspaceId)
            ->with(['product.primaryMedia', 'color:id,name,hex_code'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('sku', 'like', "%{$search}%")
                ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))))
            ->when($categoryId, fn ($query) => $query->whereHas('product', fn ($product) => $product->where('category_id', $categoryId)));
        $variants = $this->inventory->whereStatus($query, $status, $threshold)
            ->orderBy('stock_quantity')->orderBy('product_id')->orderBy('id')
            ->paginate(50)->withQueryString();

        return view('commerce::user.inventory.index', [
            'variants' => $variants,
            'reserved' => ProductVariant::reservedQuantities($variants->pluck('id')),
            'summary' => $this->inventory->summary($workspaceId),
            'categories' => Category::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(['id', 'name', 'image']),
            'filters' => ['status' => $status, 'q' => $search, 'category_id' => $categoryId],
            'threshold' => $threshold,
        ]);
    }

    public function adjust(InventoryAdjustmentRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->assertWorkspace($request, $variant->workspace_id);
        $data = $request->validated();
        $variant = $this->inventory->adjust($variant, $data['mode'], (int) $data['quantity'], $data['reason'], $data['note'] ?? null, $request->user()->id, $data['idempotency_key']);
        $stock = (int) $variant->stock_quantity;
        $reserved = ProductVariant::reservedQuantities([$variant->id])[$variant->id] ?? 0;
        $summary = $this->inventory->summary($variant->workspace_id);

        return response()->json([
            'message' => __(':sku now has :count in stock.', ['sku' => $variant->sku, 'count' => number_format($stock)]),
            'stock_quantity' => $stock,
            'reserved' => $reserved,
            'available' => $variant->availableQuantity($reserved),
            'status' => $this->inventory->status($stock, $summary['threshold']),
            'summary' => $summary,
        ]);
    }

    public function history(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->assertWorkspace($request, $variant->workspace_id);
        $movements = $this->inventory->movements($variant);
        $users = User::query()->whereIn('id', $movements->pluck('metadata.user_id')->filter()->unique())->get()->mapWithKeys(fn (User $user): array => [$user->id => $user->name]);
        $canViewOrders = $request->user()->can('commerce.orders.view');

        return response()->json(['data' => $movements->map(fn (InventoryMovement $movement): array => [
            'id' => $movement->id,
            'at' => $movement->created_at?->toDayDateTimeString(),
            'reason' => $this->label($movement),
            'note' => $movement->metadata['note'] ?? null,
            'delta' => (int) $movement->quantity_delta,
            'after' => $movement->metadata['after'] ?? null,
            'by' => $users[$movement->metadata['user_id'] ?? 0] ?? null,
            'order_url' => $movement->order_id && $canViewOrders ? route('user.commerce.orders.show', $movement->order_id) : null,
        ])->values()]);
    }

    protected function label(InventoryMovement $movement): string
    {
        return match ($movement->reason) {
            'manual_adjustment' => match ($movement->metadata['type'] ?? null) {
                'restock' => __('Restocked'),
                'correction' => __('Stock count correction'),
                'damaged' => __('Damaged or lost'),
                'returned' => __('Customer return'),
                default => __('Manual change'),
            },
            'product_edit' => __('Changed in product editor'),
            'order_paid' => __('Order paid'),
            'order_cancelled' => __('Order cancelled'),
            'order_edited' => __('Order edited'),
            default => str($movement->reason)->replace('_', ' ')->ucfirst()->toString(),
        };
    }

    protected function assertWorkspace(Request $request, int $workspaceId): void
    {
        abort_unless($this->workspaces->current($request->user())?->id === $workspaceId, 404);
    }
}
