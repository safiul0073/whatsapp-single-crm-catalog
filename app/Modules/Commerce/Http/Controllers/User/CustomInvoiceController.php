<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\RejectInvoiceRequestRequest;
use App\Modules\Commerce\Http\Requests\SaveCustomInvoiceRequest;
use App\Modules\Commerce\Http\Requests\TransitionCustomInvoiceRequest;
use App\Modules\Commerce\Models\CustomInvoice;
use App\Modules\Commerce\Models\InvoiceRequest;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\CustomInvoiceService;
use App\Modules\Commerce\Services\OrderInvoiceWhatsAppService;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Services\InvoiceService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Owner screens for custom invoices and the invoice requests customers send in. */
class CustomInvoiceController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces, protected CustomInvoiceService $service) {}

    public function index(Request $request): View
    {
        $workspace = $this->workspace($request);
        $status = in_array($request->query('status'), CustomInvoice::STATUSES, true) ? $request->query('status') : null;
        $search = trim($request->string('search')->toString());

        $invoices = CustomInvoice::query()
            ->where('workspace_id', $workspace->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('number', 'like', "%{$search}%")->orWhere('bill_to_name', 'like', "%{$search}%")->orWhere('bill_to_phone', 'like', "%{$search}%")))
            ->withCount('items')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $requests = InvoiceRequest::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', InvoiceRequest::PENDING)
            ->with('customer.contact')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('commerce::user.custom-invoices.index', compact('invoices', 'requests', 'status', 'search'));
    }

    public function create(Request $request): View
    {
        $workspace = $this->workspace($request);
        $invoiceRequest = $request->filled('request')
            ? InvoiceRequest::query()->where('workspace_id', $workspace->id)->findOrFail($request->integer('request'))
            : null;
        $draft = $invoiceRequest ? $this->service->draftFromRequest($invoiceRequest) : ['items' => []];

        return $this->form($workspace, null, $draft, $invoiceRequest);
    }

    public function store(SaveCustomInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->service->save($this->workspace($request), $request->validated());

        return redirect()->route('user.commerce.custom-invoices.show', $invoice)
            ->with('success', $invoice->isDraft() ? __('Invoice :number saved as a draft.', ['number' => $invoice->number]) : __('Invoice :number issued.', ['number' => $invoice->number]));
    }

    public function show(Request $request, CustomInvoice $customInvoice): View
    {
        $this->assertInvoice($request, $customInvoice);
        $customInvoice->load(['items', 'customer.contact', 'request']);

        return view('commerce::user.custom-invoices.show', ['invoice' => $customInvoice]);
    }

    public function edit(Request $request, CustomInvoice $customInvoice): View|RedirectResponse
    {
        $workspace = $this->workspace($request);
        $this->assertInvoice($request, $customInvoice);
        if (! $customInvoice->isDraft()) {
            return redirect()->route('user.commerce.custom-invoices.show', $customInvoice)->with('warning', __('Only draft invoices can be edited.'));
        }
        $customInvoice->load(['items', 'request']);

        return $this->form($workspace, $customInvoice, [
            'customer_id' => $customInvoice->customer_id,
            'invoice_request_id' => $customInvoice->invoice_request_id,
            'bill_to_name' => $customInvoice->bill_to_name,
            'bill_to_phone' => $customInvoice->bill_to_phone,
            'bill_to_email' => $customInvoice->bill_to_email,
            'bill_to_address' => $customInvoice->bill_to_address,
            'discount_amount' => $this->trim($customInvoice->discount_amount),
            'tax_amount' => $this->trim($customInvoice->tax_amount),
            'shipping_amount' => $this->trim($customInvoice->shipping_amount),
            'notes' => $customInvoice->notes,
            'due_date' => $customInvoice->due_date?->format('Y-m-d'),
            'items' => $customInvoice->items->map(fn ($item): array => [
                'product_id' => $item->product_id, 'variant_id' => $item->variant_id, 'name' => $item->name,
                'description' => $item->description, 'quantity' => $item->quantity, 'unit_price' => $this->trim($item->unit_price),
            ])->all(),
        ], $customInvoice->request);
    }

    public function update(SaveCustomInvoiceRequest $request, CustomInvoice $customInvoice): RedirectResponse
    {
        $this->assertInvoice($request, $customInvoice);
        $invoice = $this->service->save($this->workspace($request), $request->validated(), $customInvoice);

        return redirect()->route('user.commerce.custom-invoices.show', $invoice)->with('success', __('Invoice updated.'));
    }

    public function transition(TransitionCustomInvoiceRequest $request, CustomInvoice $customInvoice): RedirectResponse
    {
        $this->assertInvoice($request, $customInvoice);
        $this->service->transition($customInvoice, $request->validated('status'));

        return back()->with('success', __('Invoice marked :status.', ['status' => __($customInvoice->status)]));
    }

    public function send(Request $request, CustomInvoice $customInvoice, OrderInvoiceWhatsAppService $whatsapp): RedirectResponse
    {
        $this->assertInvoice($request, $customInvoice);
        if ($customInvoice->isDraft()) {
            $this->service->transition($customInvoice, CustomInvoice::SENT);
        }
        $result = $whatsapp->sendCustom($customInvoice);

        return back()->with($result['status'] === OrderInvoiceWhatsAppService::SENT ? 'success' : 'warning', $result['message']);
    }

    public function regenerateLink(Request $request, CustomInvoice $customInvoice): RedirectResponse
    {
        $this->assertInvoice($request, $customInvoice);
        $this->service->regenerateToken($customInvoice);

        return back()->with('success', __('A new invoice link was created. The old link no longer works.'));
    }

    public function pdf(Request $request, CustomInvoice $customInvoice, InvoiceService $invoices): Response
    {
        $this->assertInvoice($request, $customInvoice);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($invoices->customPdf($customInvoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$customInvoice->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, CustomInvoice $customInvoice): RedirectResponse
    {
        $this->assertInvoice($request, $customInvoice);
        if (! $customInvoice->isDraft()) {
            return back()->with('warning', __('Issued invoices cannot be deleted. Cancel them instead.'));
        }
        $customInvoice->request?->update(['status' => InvoiceRequest::PENDING, 'resolved_at' => null]);
        $customInvoice->delete();

        return redirect()->route('user.commerce.custom-invoices.index')->with('success', __('Draft deleted.'));
    }

    public function rejectRequest(RejectInvoiceRequestRequest $request, InvoiceRequest $invoiceRequest): RedirectResponse
    {
        abort_unless($invoiceRequest->workspace_id === $this->workspace($request)->id, 404);
        $this->service->rejectRequest($invoiceRequest, $request->validated('owner_note'));

        return back()->with('success', __('Request declined.'));
    }

    /**
     * Customer picker search for the invoice form.
     */
    public function customers(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $search = trim($request->string('q')->toString());

        $customers = Customer::query()
            ->where('workspace_id', $workspace->id)
            ->with('contact')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('verified_phone', 'like', "%{$search}%")
                ->orWhereHas('contact', fn ($contact) => $contact->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => (string) ($customer->contact?->name ?? $customer->verified_phone),
                'phone' => $customer->contact?->phone ?? $customer->verified_phone,
                'email' => $customer->contact?->email,
            ]);

        return response()->json(['data' => $customers]);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function form(Workspace $workspace, ?CustomInvoice $invoice, array $draft, ?InvoiceRequest $invoiceRequest): View
    {
        $products = Product::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->with('variants')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $this->trim($product->single_piece_price ?? $product->variants->first()?->price),
                'variants' => $product->variants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'label' => collect($variant->attributes ?? [])->filter(fn ($value): bool => is_scalar($value) && $value !== '')->implode(' / ') ?: $variant->sku,
                    'price' => $this->trim($variant->price),
                ])->values()->all(),
            ])->values();

        $customer = ! empty($draft['customer_id']) ? Customer::query()->with('contact')->where('workspace_id', $workspace->id)->find($draft['customer_id']) : null;

        return view('commerce::user.custom-invoices.form', [
            'invoice' => $invoice,
            'invoiceRequest' => $invoiceRequest?->loadMissing('customer.contact'),
            'draft' => $draft,
            'products' => $products,
            'customer' => $customer ? ['id' => $customer->id, 'name' => (string) ($customer->contact?->name ?? $customer->verified_phone), 'phone' => $customer->contact?->phone ?? $customer->verified_phone] : null,
            'currency' => $invoice?->currency ?? StoreOrderSetting::catalogCurrency($workspace->id),
        ]);
    }

    private function trim(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return rtrim(rtrim(OrderMoney::decimal(OrderMoney::minor((string) $amount, 4), 4), '0'), '.');
    }

    private function workspace(Request $request): Workspace
    {
        return $this->workspaces->current($request->user());
    }

    private function assertInvoice(Request $request, CustomInvoice $invoice): void
    {
        abort_unless($invoice->workspace_id === $this->workspace($request)->id, 404);
    }
}
