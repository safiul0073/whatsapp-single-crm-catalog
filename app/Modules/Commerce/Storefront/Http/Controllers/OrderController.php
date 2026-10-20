<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Controllers\Api\StoreOrderController;
use App\Modules\Commerce\Http\Requests\PaymentEvidenceRequest;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderWorkflowService;
use App\Modules\Commerce\Services\UnifiedOrderService;
use App\Modules\Commerce\Storefront\Services\InvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private function query(Request $r): Builder
    {
        $c = $r->attributes->get('store_customer');

        return Order::query()->where('workspace_id', $c->workspace_id)->where('contact_id', $c->contact_id);
    }

    private function find(Request $r, string $ref): Order
    {
        return $this->query($r)->where('submission_reference', $ref)->firstOrFail();
    }

    public function index(Request $r, UnifiedOrderService $s): JsonResponse
    {
        $p = $this->query($r)->latest()->paginate(20);

        return response()->json(['data' => $p->map(fn ($o) => $s->payload($o)), 'meta' => ['last_page' => $p->lastPage(), 'current_page' => $p->currentPage()]]);
    }

    public function show(Request $r, string $ref, UnifiedOrderService $s): JsonResponse
    {
        return response()->json(['data' => $s->payload($this->find($r, $ref))]);
    }

    public function cancel(Request $r, string $ref, OrderWorkflowService $workflow, UnifiedOrderService $s): JsonResponse
    {
        return DB::transaction(function () use ($r, $ref, $workflow, $s): JsonResponse {
            $order = $this->query($r)->where('submission_reference', $ref)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($order->status, ['requested', 'needs_details', 'quoted', 'awaiting_payment']) && $order->payment_state === 'unpaid', 422, 'Contact the store to cancel this order.');

            return response()->json(['data' => $s->payload($workflow->transition($order, 'cancelled'))]);
        });
    }

    public function evidence(PaymentEvidenceRequest $r, string $ref, StoreOrderController $legacy): JsonResponse
    {
        $this->find($r, $ref);

        return $legacy->evidence($r, $ref);
    }

    public function invoice(Request $r, string $ref, InvoiceService $invoice): Response
    {
        $order = $this->find($r, $ref);

        return response($invoice->pdf($order), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="invoice-'.$order->number.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function track(Request $r): JsonResponse
    {
        $ref = trim((string) $r->query('ref'));
        abort_if($ref === '' || strlen($ref) > 100, 422, 'Tracking reference is required.');

        $order = Order::query()
            ->where('workspace_id', $r->attributes->get('store_workspace')->id)
            ->where(function ($q) use ($ref): void {
                $q->where('number', $ref)
                    ->orWhere('tracking_code', $ref)
                    ->orWhere('tracking_number', $ref)
                    ->orWhere('submission_reference', $ref);
            })
            ->first();

        abort_unless($order, 404, 'No order or shipment found matching this tracking reference.');

        return response()->json([
            'data' => [
                'number' => $order->number,
                'submission_reference' => $order->submission_reference,
                'status' => $order->status,
                'payment_state' => $order->payment_state,
                'tracking_code' => $order->tracking_code,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url,
                'delivery_method' => $order->delivery_method,
                'timeline' => $order->trackingTimeline(),
            ],
        ]);
    }
}
