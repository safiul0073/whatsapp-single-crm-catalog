<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\InvoiceLineRequest;
use App\Modules\Shipping\Http\Requests\InvoiceRequest;
use App\Modules\Shipping\Http\Requests\ResolveInvoiceLineRequest;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\InvoiceLine;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingInvoice;
use App\Modules\Shipping\Services\InvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends ShippingBaseController
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter');
        $search = trim((string) $request->query('search'));

        $invoices = ShippingInvoice::query()
            ->with(['agent', 'carrierAccount'])
            ->withCount(['lines', 'lines as open_lines_count' => fn ($lines) => $lines->whereIn('match_status', ['unmatched', 'duplicate'])])
            ->where('workspace_id', $this->workspaceId($request))
            ->when(array_key_exists($filter, ShippingInvoice::STATUSES), fn ($query) => $query->where('status', $filter))
            ->when($filter === 'unpaid', fn ($query) => $query->where('payment_status', '!=', 'paid'))
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $match->where('invoice_number', 'like', $like)
                    ->orWhereHas('agent', fn ($agent) => $agent->where('name', 'like', $like))
                    ->orWhereHas('lines', fn ($lines) => $lines->where('tracking_number', 'like', $like));
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('shipping::user.management.invoices.index', [
            'invoices' => $invoices,
            'filter' => $filter,
            'search' => $search,
            'canManage' => $request->user()->can('shipping.billing.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $workspaceId = $this->workspaceId($request);

        return view('shipping::user.management.invoices.create', [
            'agents' => ShippingAgent::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'carrierAccounts' => CarrierAccount::query()->where('workspace_id', $workspaceId)->orderBy('name')->get(),
            'storeCurrency' => $this->storeCurrency($request),
        ]);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $result = $this->invoices->create($this->workspaceId($request), $request->safe()->except('file'), $request->file('file'), $request->user());
        $invoice = $result['invoice'];

        if ($result['imported'] > 0) {
            $summary = $this->invoices->reconcile($invoice, $request->user());

            return redirect()->route('user.shipping.invoices.show', $invoice)->with($invoice->refresh()->status === 'reconciled' ? 'success' : 'warning', __(':imported line(s) imported, :skipped skipped. :matched matched, :unmatched without a shipment, :duplicates possible duplicate(s), :over over the approved cost.', [
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'matched' => $summary['matched'],
                'unmatched' => $summary['unmatched'],
                'duplicates' => $summary['duplicates'],
                'over' => $summary['over_approved'],
            ]));
        }

        return redirect()->route('user.shipping.invoices.show', $invoice)->with('success', __('Invoice saved. Add its lines, then reconcile it.'));
    }

    public function show(Request $request, ShippingInvoice $invoice): View
    {
        $this->assertWorkspace($request, $invoice);
        $invoice->load(['agent', 'carrierAccount', 'uploader', 'payments.recorder', 'lines.charges', 'lines.shipment']);

        return view('shipping::user.management.invoices.show', [
            'invoice' => $invoice,
            'linkable' => $request->user()->can('shipping.billing.manage') ? $this->invoices->linkableShipments($invoice) : collect(),
            'canManage' => $request->user()->can('shipping.billing.manage'),
            'storeCurrency' => $this->storeCurrency($request),
        ]);
    }

    public function file(Request $request, ShippingInvoice $invoice): BinaryFileResponse
    {
        $this->assertWorkspace($request, $invoice);
        abort_unless($invoice->file_path && Storage::disk(InvoiceService::DISK)->exists($invoice->file_path), 404);

        return response()->download(Storage::disk(InvoiceService::DISK)->path($invoice->file_path), $invoice->original_name ?: basename($invoice->file_path));
    }

    public function reconcile(Request $request, ShippingInvoice $invoice): RedirectResponse
    {
        $this->assertWorkspace($request, $invoice);
        $summary = $this->invoices->reconcile($invoice, $request->user());

        return back()->with($invoice->refresh()->status === 'reconciled' ? 'success' : 'warning', __('Reconciled: :matched matched, :unmatched without a shipment, :duplicates possible duplicate(s), :over over the approved cost.', [
            'matched' => $summary['matched'],
            'unmatched' => $summary['unmatched'],
            'duplicates' => $summary['duplicates'],
            'over' => $summary['over_approved'],
        ]));
    }

    public function storeLine(InvoiceLineRequest $request, ShippingInvoice $invoice): RedirectResponse
    {
        $this->assertWorkspace($request, $invoice);
        $this->invoices->addLine($invoice, $request->lineData(), $request->user());
        $summary = $this->invoices->reconcile($invoice, $request->user());

        return back()->with($invoice->refresh()->status === 'reconciled' ? 'success' : 'warning', __('Line added and reconciled: :matched matched, :unmatched without a shipment, :duplicates possible duplicate(s), :over over the approved cost.', [
            'matched' => $summary['matched'],
            'unmatched' => $summary['unmatched'],
            'duplicates' => $summary['duplicates'],
            'over' => $summary['over_approved'],
        ]));
    }

    public function resolveLine(ResolveInvoiceLineRequest $request, InvoiceLine $line): RedirectResponse
    {
        $this->assertWorkspace($request, $line);
        $ignore = $request->validated('action') === 'ignore';
        $shipment = $ignore ? null : Shipment::query()->where('workspace_id', $line->workspace_id)->findOrFail((int) $request->validated('shipment_id'));
        $this->invoices->resolveLine($line, $shipment, $ignore, $request->user());

        return back()->with('success', $ignore ? __('Line set aside.') : __('Line linked to :reference.', ['reference' => $shipment->reference]));
    }

    public function destroyLine(Request $request, InvoiceLine $line): RedirectResponse
    {
        $this->assertWorkspace($request, $line);
        $this->invoices->deleteLine($line, $request->user());

        return back()->with('success', __('Line removed.'));
    }

    public function destroy(Request $request, ShippingInvoice $invoice): RedirectResponse
    {
        $this->assertWorkspace($request, $invoice);
        $this->invoices->delete($invoice, $request->user());

        return redirect()->route('user.shipping.invoices.index')->with('success', __('Invoice removed.'));
    }
}
