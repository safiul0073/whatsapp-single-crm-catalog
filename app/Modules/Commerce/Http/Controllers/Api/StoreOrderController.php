<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\CreateUnifiedOrderRequest;
use App\Modules\Commerce\Http\Requests\OrderHistoryRequest;
use App\Modules\Commerce\Http\Requests\PaymentEvidenceRequest;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreOrderController extends Controller
{
    public function settings(Request $request): JsonResponse
    {
        $settings = StoreOrderSetting::forWorkspace($request->attributes->get('store_workspace')->id);

        return response()->json(['currency' => $settings->currency, 'precision' => $settings->precision(), 'payment_instructions' => $settings->payment_instructions, 'payment_methods' => StoreOrderSetting::paymentMethods($settings->workspace_id, true)]);
    }

    public function preview(CreateUnifiedOrderRequest $request, UnifiedOrderService $orders): JsonResponse
    {
        return response()->json(['data' => $orders->preview($request->attributes->get('store_workspace'), $request->validated())]);
    }

    public function store(CreateUnifiedOrderRequest $request, UnifiedOrderService $orders): JsonResponse
    {
        $data = $request->validated();
        $data['source'] = $request->header('X-Order-Source') === 'storefront_whatsapp' ? 'storefront_whatsapp' : 'storefront_checkout';
        $data['draft'] = false;
        unset($data['contact_id']);
        $preview = $orders->preview($request->attributes->get('store_workspace'), $data);
        abort_if($preview['shipping_quote_required'], 422, 'No shipping rate covers this destination and weight.');
        $order = $orders->create($request->attributes->get('store_workspace'), $data);

        return response()->json(['data' => $orders->payload($order)], 201);
    }

    public function show(Request $request, string $reference, UnifiedOrderService $orders): JsonResponse
    {
        return response()->json(['data' => $orders->payload($this->find($request, $reference))]);
    }

    public function history(OrderHistoryRequest $request, UnifiedOrderService $orders): JsonResponse
    {
        $items = Order::query()->where('workspace_id', $request->attributes->get('store_workspace')->id)->where('customer_reference', $request->string('customer_reference')->toString())->latest()->paginate(20);

        return response()->json(['data' => $items->map(fn ($order) => $orders->payload($order)), 'last_page' => $items->lastPage()]);
    }

    public function cancel(Request $request, string $reference, OrderWorkflowService $workflow, UnifiedOrderService $orders): JsonResponse
    {
        $order = $this->find($request, $reference);
        $workflow->transition($order, 'cancelled');

        return response()->json(['data' => $orders->payload($order->fresh())]);
    }

    public function evidence(PaymentEvidenceRequest $request, string $reference): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $reference, $data): void {
            $order = Order::query()->where('workspace_id', $request->attributes->get('store_workspace')->id)->where('submission_reference', $reference)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'awaiting_payment' && ! $order->shipping_quote_required && $order->payment_state === 'unpaid', 422, 'Payment evidence can be submitted after the final quote.');
            $method = collect(StoreOrderSetting::paymentMethods($order->workspace_id, true))->firstWhere('id', $data['payment_method_id']);
            abort_unless($method, 422, 'This payment method is unavailable.');
            $receipts = [];
            try {
                foreach ($request->file('receipts') as $file) {
                    $receipts[] = ['path' => $file->store('commerce-receipts', 'local'), 'name' => $file->getClientOriginalName()];
                }
                $order->update(['payment_state' => 'submitted', 'payment_evidence' => [
                    'method' => $method, 'transaction_id' => $data['transaction_id'], 'fields' => array_intersect_key($data['fields'] ?? [], array_flip(array_column($method['fields'] ?? [], 'name'))),
                    'note' => $data['note'] ?? null, 'receipts' => $receipts,
                ]]);
            } catch (\Throwable $exception) {
                foreach ($receipts as $receipt) {
                    Storage::disk('local')->delete($receipt['path']);
                }
                throw $exception;
            }
            app(UnifiedOrderService::class)->event($order, 'payment_submitted', 'Payment evidence submitted');
        });

        return response()->json(['success' => true]);
    }

    private function find(Request $request, string $reference): Order
    {
        return Order::query()->where('workspace_id', $request->attributes->get('store_workspace')->id)->where('submission_reference', $reference)->firstOrFail();
    }
}
