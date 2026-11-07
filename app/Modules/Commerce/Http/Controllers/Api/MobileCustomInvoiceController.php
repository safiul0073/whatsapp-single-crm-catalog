<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\RejectInvoiceRequestRequest;
use App\Modules\Commerce\Http\Requests\SaveCustomInvoiceRequest;
use App\Modules\Commerce\Http\Requests\TransitionCustomInvoiceRequest;
use App\Modules\Commerce\Models\CustomInvoice;
use App\Modules\Commerce\Models\InvoiceRequest;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\CustomInvoiceService;
use App\Modules\Commerce\Services\OrderInvoiceWhatsAppService;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Store-owner app endpoints for custom invoices and customer invoice requests. */
class MobileCustomInvoiceController extends Controller
{
    public function __construct(protected CustomInvoiceService $service) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('commerce.invoices.view'), 403);
        $workspaceId = $this->workspaceId($request);
        $status = in_array($request->query('status'), CustomInvoice::STATUSES, true) ? $request->query('status') : null;

        $page = CustomInvoice::query()
            ->where('workspace_id', $workspaceId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with('items')
            ->latest('id')
            ->paginate(25);

        $requests = InvoiceRequest::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', InvoiceRequest::PENDING)
            ->with('customer.contact')
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $page->getCollection()->map(fn (CustomInvoice $invoice): array => $this->payload($invoice))->values(),
            'requests' => $requests->map(fn (InvoiceRequest $item): array => [
                'id' => $item->id,
                'customer_name' => $item->customer?->contact?->name ?? $item->customer?->verified_phone,
                'customer_phone' => $item->customer?->contact?->phone ?? $item->customer?->verified_phone,
                'message' => $item->message,
                'items' => $item->items ?? [],
                'created_at' => $item->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(Request $request, string $workspace, string $invoice): JsonResponse
    {
        abort_unless($request->user()->can('commerce.invoices.view'), 403);

        return response()->json(['data' => $this->payload($this->owned($request, $invoice)->load('items'), withItems: true)]);
    }

    public function store(SaveCustomInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->service->save(Workspace::query()->findOrFail($this->workspaceId($request)), $request->validated());

        return response()->json(['data' => $this->payload($invoice, withItems: true)], 201);
    }

    public function transition(TransitionCustomInvoiceRequest $request, string $workspace, string $invoice): JsonResponse
    {
        $model = $this->service->transition($this->owned($request, $invoice), $request->validated('status'));

        return response()->json(['data' => $this->payload($model->load('items'), withItems: true)]);
    }

    public function send(Request $request, string $workspace, string $invoice, OrderInvoiceWhatsAppService $whatsapp): JsonResponse
    {
        abort_unless($request->user()->can('commerce.invoices.manage'), 403);
        $model = $this->owned($request, $invoice);
        if ($model->isDraft()) {
            $this->service->transition($model, CustomInvoice::SENT);
        }
        $result = $whatsapp->sendCustom($model);

        return response()->json([
            'sent' => $result['status'] === OrderInvoiceWhatsAppService::SENT,
            'message' => $result['message'],
            'data' => $this->payload($model->refresh()->load('items'), withItems: true),
        ]);
    }

    /**
     * Prefilled invoice lines for answering a customer request.
     */
    public function draft(Request $request, string $workspace, string $invoiceRequest): JsonResponse
    {
        abort_unless($request->user()->can('commerce.invoices.manage'), 403);

        return response()->json(['data' => $this->service->draftFromRequest($this->ownedRequest($request, $invoiceRequest))]);
    }

    public function reject(RejectInvoiceRequestRequest $request, string $workspace, string $invoiceRequest): JsonResponse
    {
        $this->service->rejectRequest($this->ownedRequest($request, $invoiceRequest), $request->validated('owner_note'));

        return response()->json(['message' => __('Request declined.')]);
    }

    /**
     * Product picker for the invoice builder.
     */
    public function products(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('commerce.invoices.manage'), 403);
        $search = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->where('workspace_id', $this->workspaceId($request))
            ->where('status', 'active')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->with('variants')
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $this->decimal($product->single_piece_price ?? $product->variants->first()?->price),
            ]);

        return response()->json(['data' => $products]);
    }

    private function workspaceId(Request $request): int
    {
        return (int) $request->attributes->get('mobile_workspace_id');
    }

    private function owned(Request $request, string $invoice): CustomInvoice
    {
        return CustomInvoice::query()->where('workspace_id', $this->workspaceId($request))->findOrFail((string) ($request->route('invoice') ?? $invoice));
    }

    private function ownedRequest(Request $request, string $invoiceRequest): InvoiceRequest
    {
        return InvoiceRequest::query()->where('workspace_id', $this->workspaceId($request))->findOrFail((string) ($request->route('invoiceRequest') ?? $invoiceRequest));
    }

    private function decimal(mixed $amount): ?string
    {
        return $amount === null ? null : OrderMoney::decimal(OrderMoney::minor((string) $amount, 2), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CustomInvoice $invoice, bool $withItems = false): array
    {
        $precision = $invoice->moneyPrecision();
        $money = fn ($amount): string => OrderMoney::decimal(OrderMoney::minor($amount ?? 0, $precision), $precision);

        $data = [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'customer_id' => $invoice->customer_id,
            'bill_to_name' => $invoice->bill_to_name,
            'bill_to_phone' => $invoice->bill_to_phone,
            'subtotal' => $money($invoice->subtotal),
            'discount_amount' => $money($invoice->discount_amount),
            'tax_amount' => $money($invoice->tax_amount),
            'shipping_amount' => $money($invoice->shipping_amount),
            'total' => $money($invoice->total),
            'item_count' => $invoice->items->count(),
            'notes' => $invoice->notes,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'sent_at' => $invoice->sent_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'created_at' => $invoice->created_at?->toIso8601String(),
            'url' => $invoice->isDraft() ? null : $invoice->publicUrl(),
            'download_url' => $invoice->isDraft() ? null : $invoice->publicUrl(download: true),
        ];
        if ($withItems) {
            $data['items'] = $invoice->items->map(fn ($item): array => [
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $money($item->unit_price),
                'line_total' => $money($item->line_total),
                'product_id' => $item->product_id,
            ])->values()->all();
        }

        return $data;
    }
}
