<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Storefront\Http\Requests\CustomerOrderEditRequest;
use App\Modules\Commerce\Storefront\Http\Requests\OrderEditRequestRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Services\OrderEditRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Customer side of order edit requests: ask, preview the new total, and use the one approved edit.
 */
class OrderEditController extends Controller
{
    public function __construct(private OrderEditRequestService $edits, private UnifiedOrderService $orders) {}

    public function store(OrderEditRequestRequest $r, string $ref): JsonResponse
    {
        $key = (string) $r->header('Idempotency-Key', (string) Str::uuid());
        abort_unless(Str::isUuid($key), 422, 'Idempotency-Key must be a UUID.');
        $order = $this->find($r, $ref);
        $this->edits->submit($this->customer($r), $order, $r->validated('note'), $key);

        return response()->json(['data' => $this->payload($order->fresh())], 201);
    }

    public function preview(CustomerOrderEditRequest $r, string $ref): JsonResponse
    {
        return response()->json(['data' => $this->edits->preview($this->find($r, $ref), $r->attributes->get('store_workspace'), $r->validated())]);
    }

    public function update(CustomerOrderEditRequest $r, string $ref): JsonResponse
    {
        $order = $this->edits->apply($this->customer($r), $this->find($r, $ref), $r->attributes->get('store_workspace'), $r->validated());

        return response()->json(['data' => $this->payload($order)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Order $order): array
    {
        return array_merge($this->orders->payload($order), $this->edits->customerPayload($order));
    }

    private function customer(Request $r): Customer
    {
        return $r->attributes->get('store_customer');
    }

    private function find(Request $r, string $ref): Order
    {
        $c = $this->customer($r);

        return Order::query()->where('workspace_id', $c->workspace_id)->where('contact_id', $c->contact_id)->where('submission_reference', $ref)->firstOrFail();
    }
}
