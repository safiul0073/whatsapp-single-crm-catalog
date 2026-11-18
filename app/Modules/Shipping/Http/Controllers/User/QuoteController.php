<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\QuoteStatusRequest;
use App\Modules\Shipping\Http\Requests\StoreQuoteRequest;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingQuote;
use App\Modules\Shipping\Services\QuoteApprovalService;
use App\Modules\Shipping\Services\QuoteService;
use App\Modules\Shipping\Services\Rates\CourierApiRateProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuoteController extends ShippingBaseController
{
    public function __construct(private QuoteService $quotes) {}

    public function requestRates(Request $request, Shipment $shipment, CourierApiRateProvider $couriers): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $created = $this->quotes->requestRates($shipment, $request->user());
        $failures = collect($couriers->failures())->map(fn (array $failure): string => $failure['account'].': '.$failure['message']);

        $redirect = back()->with($created->isEmpty() ? 'error' : 'success', $created->isEmpty()
            ? __('No live courier rate or active agent rate chart covers this destination and weight. Add a quotation manually.')
            : __(':count quotation(s) received.', ['count' => $created->count()]));

        return $failures->isEmpty() ? $redirect : $redirect->with('warning', __('Some couriers did not return a rate — :details', ['details' => $failures->implode(' | ')]));
    }

    public function store(StoreQuoteRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->quotes->store($shipment, $request->quoteData($this->workspaceId($request)), $request->user());

        return back()->with('success', __('Quotation added. It stays unverified until someone confirms the price.'));
    }

    public function status(QuoteStatusRequest $request, ShippingQuote $quote): RedirectResponse
    {
        $this->assertWorkspace($request, $quote);
        $this->quotes->setStatus($quote, $request->validated('status'), $request->user());

        return back()->with('success', __('Quotation updated.'));
    }

    public function approve(Request $request, Shipment $shipment, ShippingQuote $quote, QuoteApprovalService $approvals): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->assertWorkspace($request, $quote);
        $approvals->approve($shipment, $quote, $request->user(), $request->boolean('override'));

        return back()->with('success', __('Provider approved for this shipment.'));
    }
}
