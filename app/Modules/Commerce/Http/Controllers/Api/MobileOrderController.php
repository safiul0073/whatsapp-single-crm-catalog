<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\TransitionOrderRequest;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderBoxItem;
use App\Modules\Commerce\Services\OrderInvoiceWhatsAppService;
use App\Modules\Commerce\Services\OrderPackingService;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MobileOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('commerce.orders.view'), 403);
        $workspaceId = (int) $request->attributes->get('mobile_workspace_id');
        $status = trim((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        $orders = Order::query()
            ->where('workspace_id', $workspaceId)
            ->with('contact:id,name,phone,email')
            ->when($status !== '' && $status !== 'all', function (Builder $query) use ($status): void {
                if ($status === 'new') {
                    $query->whereIn('status', ['requested', 'needs_details', 'quoted', 'awaiting_payment']);

                    return;
                }

                if ($status === 'active_fulfillment') {
                    $query->whereIn('status', ['paid', 'processing', 'packed']);

                    return;
                }

                $query->where('status', $status);
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('number', 'like', "%{$search}%")
                        ->orWhere('tracking_code', 'like', "%{$search}%")
                        ->orWhere('tracking_number', 'like', "%{$search}%")
                        ->orWhere('submission_reference', 'like', "%{$search}%")
                        ->orWhereHas('contact', function (Builder $contactQuery) use ($search): void {
                            $contactQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(25);

        $rawCounts = Order::query()
            ->where('workspace_id', $workspaceId)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $countFor = static fn (array $statuses): int => collect($statuses)
            ->sum(static fn (string $key): int => (int) ($rawCounts[$key] ?? 0));

        $payload = $orders->toArray();
        $payload['data'] = array_map(fn (array $row): array => $this->redact($request, $row), $payload['data']);
        $payload['counts'] = [
            'all' => (int) $rawCounts->sum(),
            'new' => $countFor(['requested', 'needs_details', 'quoted', 'awaiting_payment']),
            'awaiting_payment' => (int) ($rawCounts['awaiting_payment'] ?? 0),
            'paid' => (int) ($rawCounts['paid'] ?? 0),
            'processing' => (int) ($rawCounts['processing'] ?? 0),
            'packed' => (int) ($rawCounts['packed'] ?? 0),
            'active_fulfillment' => $countFor(['paid', 'processing', 'packed']),
            'shipped' => (int) ($rawCounts['shipped'] ?? 0),
            'completed' => (int) ($rawCounts['completed'] ?? 0),
            'cancelled' => (int) ($rawCounts['cancelled'] ?? 0),
        ];

        return response()->json($payload);
    }

    public function show(Request $request, string $workspace, string $order, UnifiedOrderService $orders): JsonResponse
    {
        abort_unless($request->user()->can('commerce.orders.view'), 403);
        $orderId = (string) ($request->route('order') ?? $order);
        $model = Order::query()->where('workspace_id', $request->attributes->get('mobile_workspace_id'))->findOrFail($orderId);

        return response()->json(['data' => $this->redact($request, $orders->payload($model))]);
    }

    public function invoice(Request $request, string $workspace, string $order): JsonResponse
    {
        abort_unless($request->user()->can('commerce.orders.view'), 403);
        $model = $this->ownedOrder($request, $order)->ensureInvoice();

        return response()->json(['data' => $this->invoicePayload($model)]);
    }

    public function sendInvoice(Request $request, string $workspace, string $order, OrderInvoiceWhatsAppService $invoices): JsonResponse
    {
        abort_unless($request->user()->can('commerce.orders.update'), 403);
        $model = $this->ownedOrder($request, $order)->loadMissing('contact');
        $result = $invoices->send($model, manual: true);

        return response()->json([
            'sent' => $result['status'] === OrderInvoiceWhatsAppService::SENT,
            'message' => $result['message'],
            'data' => $this->invoicePayload($model->refresh()),
        ]);
    }

    private function ownedOrder(Request $request, string $order): Order
    {
        $orderId = (string) ($request->route('order') ?? $order);

        return Order::query()->where('workspace_id', $request->attributes->get('mobile_workspace_id'))->findOrFail($orderId);
    }

    /**
     * @return array{number: ?string, url: string, download_url: string, sent_at: ?string}
     */
    private function invoicePayload(Order $order): array
    {
        return [
            'number' => $order->invoice_number,
            'url' => $order->invoiceUrl(),
            'download_url' => $order->invoiceUrl(download: true),
            'sent_at' => $order->invoice_sent_at?->toIso8601String(),
        ];
    }

    public function transition(
        TransitionOrderRequest $request,
        string $workspace,
        string $order,
        OrderWorkflowService $workflow,
        UnifiedOrderService $orders,
        OrderPackingService $packing
    ): JsonResponse {
        $orderId = (string) ($request->route('order') ?? $order);
        $model = Order::query()->where('workspace_id', $request->attributes->get('mobile_workspace_id'))->findOrFail($orderId);
        $targetStatus = (string) $request->validated('status');

        if ($targetStatus === 'packed' && in_array($model->status, ['paid', 'processing'], true)) {
            $this->autoPackOrderForMobile($model, $packing);
            $model = $model->fresh();
        }

        $updated = $workflow->transition($model, $targetStatus, $request->safe()->except('status'));

        return response()->json(['data' => $orders->payload($updated)]);
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function redact(Request $request, array $order): array
    {
        $user = $request->user();
        $hidden = [];

        if (! $user->can('commerce.orders.customer.view')) {
            $hidden = [...$hidden, 'contact', 'customer_snapshot', 'shipping_address', 'customer'];
        }

        if (! $user->can('commerce.orders.summary.view')) {
            $hidden = [...$hidden, 'subtotal', 'shipping_amount', 'discount_amount', 'total'];
        }

        if (! $user->can('commerce.orders.payments.view')) {
            $hidden = [...$hidden, 'payment_evidence', 'payment_url', 'payment_instructions', 'payment_state'];
        }

        return Arr::except($order, $hidden);
    }

    private function autoPackOrderForMobile(Order $order, OrderPackingService $packing): void
    {
        $order->loadMissing(['items', 'groups', 'boxes']);
        $unallocatedRetail = [];

        foreach ($order->items as $item) {
            $group = $order->groups->firstWhere('id', $item->group_id);
            if ($group && $group->mode === 'wholesale') {
                continue;
            }

            $allocated = (int) OrderBoxItem::query()->where('order_item_id', $item->id)->sum('quantity');
            $remaining = $item->quantity - $allocated;
            if ($remaining > 0) {
                $unallocatedRetail[$item->id] = $remaining;
            }
        }

        if ($unallocatedRetail !== []) {
            $packing->addRetailBox($order, $unallocatedRetail);
        }

        foreach ($order->boxes()->whereNull('packed_at')->pluck('id') as $boxId) {
            $packing->markPacked($order, (int) $boxId);
        }
    }
}
