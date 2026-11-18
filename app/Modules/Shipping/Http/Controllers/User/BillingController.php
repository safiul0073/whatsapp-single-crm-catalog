<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\BillingRequest;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\ShipmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends ShippingBaseController
{
    public function __construct(private ShipmentService $shipments) {}

    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter');

        $shipments = Shipment::query()
            ->with(['order', 'agent', 'carrierAccount', 'charges'])
            ->where('workspace_id', $this->workspaceId($request))
            ->whereNotNull('approved_quote_id')
            ->when($filter === 'review', fn ($query) => $query->where('needs_review', true))
            ->when($filter === 'unbilled', fn ($query) => $query->whereNull('final_cost'))
            ->when($filter === 'unpaid', fn ($query) => $query->whereNotNull('final_cost')->where('payment_status', '!=', 'paid'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('shipping::user.management.billing.index', [
            'shipments' => $shipments,
            'filter' => $filter,
            'variances' => $shipments->getCollection()->mapWithKeys(fn (Shipment $shipment): array => [$shipment->id => $this->shipments->variance($shipment)]),
        ]);
    }

    public function update(BillingRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $billed = $this->shipments->recordBilling($shipment, $request->billingData(), $request->user());

        return back()->with($billed->needs_review ? 'error' : 'success', $billed->needs_review
            ? __('Billed amount saved. :reason — the owner has been notified.', ['reason' => $billed->review_reason])
            : __('Billed amount saved and reconciled.'));
    }

    public function review(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->shipments->resolveReview($shipment, $request->user(), $request->string('note')->limit(200)->toString() ?: null);

        return back()->with('success', __('Unexpected charge approved.'));
    }
}
