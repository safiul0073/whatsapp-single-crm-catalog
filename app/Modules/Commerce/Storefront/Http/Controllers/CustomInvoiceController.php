<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\CustomInvoice;
use App\Modules\Commerce\Models\InvoiceRequest;
use App\Modules\Commerce\Services\CustomInvoiceService;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Http\Requests\StoreInvoiceRequestRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/** Customer-facing custom invoices: list, view, download, and request a new one. */
class CustomInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = CustomInvoice::query()
            ->where('workspace_id', $this->customer($request)->workspace_id)
            ->where('customer_id', $this->customer($request)->id)
            ->visibleToCustomer()
            ->with('items')
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (CustomInvoice $invoice): array => $this->payload($invoice))->values(),
            'meta' => ['last_page' => $page->lastPage(), 'current_page' => $page->currentPage()],
        ]);
    }

    public function show(Request $request, string $number): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->find($request, $number), withItems: true)]);
    }

    public function pdf(Request $request, string $number, InvoiceService $invoices): Response
    {
        $invoice = $this->find($request, $number);

        return response($invoices->customPdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function requests(Request $request): JsonResponse
    {
        $requests = InvoiceRequest::query()
            ->where('customer_id', $this->customer($request)->id)
            ->with('invoice')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (InvoiceRequest $item): array => $this->requestPayload($item));

        return response()->json(['data' => $requests]);
    }

    public function storeRequest(StoreInvoiceRequestRequest $request, CustomInvoiceService $service): JsonResponse
    {
        $customer = $this->customer($request);
        $key = 'invoice-requests:'.$customer->id;
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'You have sent several requests recently. Please wait before sending another.');
        abort_if(
            InvoiceRequest::query()->where('customer_id', $customer->id)->where('status', InvoiceRequest::PENDING)->count() >= 5,
            422,
            'You already have 5 requests waiting. Please wait for the store to answer them.',
        );

        $invoiceRequest = $service->createRequest($customer, $request->validated());
        RateLimiter::hit($key, 3600);

        return response()->json(['data' => $this->requestPayload($invoiceRequest)], 201);
    }

    private function customer(Request $request): Customer
    {
        return $request->attributes->get('store_customer');
    }

    private function find(Request $request, string $number): CustomInvoice
    {
        $customer = $this->customer($request);

        return CustomInvoice::query()
            ->where('workspace_id', $customer->workspace_id)
            ->where('customer_id', $customer->id)
            ->visibleToCustomer()
            ->where('number', $number)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CustomInvoice $invoice, bool $withItems = false): array
    {
        $precision = $invoice->moneyPrecision();
        $money = fn ($amount): string => OrderMoney::decimal(OrderMoney::minor($amount ?? 0, $precision), $precision);

        $data = [
            'number' => $invoice->number,
            'status' => $invoice->status,
            'currency' => $invoice->currency,
            'subtotal' => $money($invoice->subtotal),
            'discount_amount' => $money($invoice->discount_amount),
            'tax_amount' => $money($invoice->tax_amount),
            'shipping_amount' => $money($invoice->shipping_amount),
            'total' => $money($invoice->total),
            'item_count' => $invoice->items->count(),
            'bill_to' => ['name' => $invoice->bill_to_name, 'phone' => $invoice->bill_to_phone, 'email' => $invoice->bill_to_email, 'address' => $invoice->bill_to_address],
            'notes' => $invoice->notes,
            'due_date' => $invoice->due_date?->toDateString(),
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'pdf_url' => $invoice->publicUrl(download: true),
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

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(InvoiceRequest $request): array
    {
        $invoice = $request->invoice && ! $request->invoice->isDraft() ? $request->invoice : null;

        return [
            'id' => $request->id,
            'status' => $request->status,
            'message' => $request->message,
            'items' => $request->items ?? [],
            'owner_note' => $request->owner_note,
            'invoice_number' => $invoice?->number,
            'created_at' => $request->created_at?->toIso8601String(),
            'resolved_at' => $request->resolved_at?->toIso8601String(),
        ];
    }
}
