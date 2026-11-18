<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Commerce\Models\Order;
use App\Modules\Shipping\Http\Requests\BookingRequest;
use App\Modules\Shipping\Http\Requests\CartonsRequest;
use App\Modules\Shipping\Http\Requests\ShipmentStatusRequest;
use App\Modules\Shipping\Http\Requests\StoreShipmentRequest;
use App\Modules\Shipping\Http\Requests\UpdateShipmentRequest;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Services\BookingService;
use App\Modules\Shipping\Services\RateComparisonService;
use App\Modules\Shipping\Services\RemoteAreaService;
use App\Modules\Shipping\Services\ShipmentService;
use App\Modules\Shipping\Services\ShipmentWeightCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentController extends ShippingBaseController
{
    public function __construct(private ShipmentService $shipments) {}

    public function overview(Request $request): View
    {
        $workspaceId = $this->workspaceId($request);
        $base = fn () => Shipment::query()->where('workspace_id', $workspaceId);

        return view('shipping::user.management.overview', [
            'counts' => [
                'pending' => $base()->whereIn('status', ['pending', 'quoted'])->count(),
                'approved' => $base()->where('status', 'approved')->count(),
                'moving' => $base()->whereIn('status', ['booked', 'in_transit', 'out_for_delivery'])->count(),
                'exceptions' => $base()->where('status', 'exception')->count(),
                'delivered' => $base()->whereIn('status', ['delivered', 'closed'])->count(),
                'review' => $base()->where('needs_review', true)->count(),
                'unpaid' => $base()->whereNotNull('final_cost')->where('payment_status', '!=', 'paid')->count(),
            ],
            'needsReview' => $base()->with(['order', 'agent', 'carrierAccount'])->where('needs_review', true)->latest('id')->limit(10)->get(),
            'recent' => $base()->with(['order', 'agent', 'carrierAccount'])->latest('id')->limit(10)->get(),
            'canViewCosts' => $request->user()->can('shipping.costs.view'),
        ]);
    }

    public function index(Request $request): View
    {
        $workspaceId = $this->workspaceId($request);
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $shipments = Shipment::query()
            ->with(['order', 'agent', 'carrierAccount'])
            ->where('workspace_id', $workspaceId)
            ->when(array_key_exists($status, Shipment::STATUSES), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $carrier = array_search(strtolower($search), array_map('strtolower', CarrierAccount::CARRIERS), true);
                $query->where(function ($match) use ($like, $carrier): void {
                    $match->where('reference', 'like', $like)
                        ->orWhere('tracking_number', 'like', $like)
                        ->orWhere('recipient_name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhereHas('order', fn ($order) => $order->where('number', 'like', $like))
                        ->orWhereHas('agent', fn ($agent) => $agent->where('name', 'like', $like)->orWhere('company_name', 'like', $like))
                        ->orWhereHas('carrierAccount', fn ($account) => $account->where('name', 'like', $like));
                    if ($carrier !== false) {
                        $match->orWhere('carrier', $carrier);
                    }
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('shipping::user.management.shipments.index', [
            'shipments' => $shipments,
            'search' => $search,
            'status' => $status,
            'canViewCosts' => $request->user()->can('shipping.costs.view'),
        ]);
    }

    public function create(Request $request): View
    {
        $workspaceId = $this->workspaceId($request);
        $search = trim((string) $request->query('order'));

        $orders = Order::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($search !== '', fn ($query) => $query->where('number', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->withSum('items as pieces', 'quantity')
            ->latest('id')
            ->limit(30)
            ->get();

        return view('shipping::user.management.shipments.create', [
            'orders' => $orders,
            'search' => $search,
            'shipmentCounts' => Shipment::query()->whereIn('order_id', $orders->pluck('id'))->selectRaw('order_id, count(*) as total')->groupBy('order_id')->pluck('total', 'order_id'),
        ]);
    }

    public function store(StoreShipmentRequest $request): RedirectResponse
    {
        $order = Order::query()->where('workspace_id', $this->workspaceId($request))->findOrFail($request->integer('order_id'));
        $shipment = $this->shipments->create($order, [], $request->user());

        return redirect()->route('user.shipping.shipments.show', $shipment)->with('success', __('Shipment :reference created.', ['reference' => $shipment->reference]));
    }

    public function show(Request $request, Shipment $shipment, ShipmentWeightCalculator $weights, RateComparisonService $comparison, RemoteAreaService $remoteAreas, BookingService $bookings): View
    {
        $this->assertWorkspace($request, $shipment);
        $shipment->load(['order.items.variant.product.gallery.media', 'order.items.variant.product.primaryMedia', 'order.items.variant.color.swatchMedia', 'cartons', 'agent', 'carrierAccount', 'approver', 'charges', 'approvedQuote', 'booker', 'voidRequester', 'documents', 'trackingEvents']);
        $user = $request->user();
        $canViewCosts = $user->can('shipping.costs.view');
        $provider = $shipment->agent ?? $shipment->carrierAccount;

        return view('shipping::user.management.shipments.show', [
            'shipment' => $shipment,
            'weight' => $shipment->cartons->isEmpty() ? null : $weights->calculate($shipment->cartons->toArray(), $provider?->dim_divisor, $provider?->weight_rounding_step),
            'flagLabels' => ShipmentWeightCalculator::flagLabels(),
            'quotes' => $canViewCosts ? $comparison->forShipment($shipment) : collect(),
            'remoteStatuses' => $remoteAreas->statusForAllProviders($shipment->workspace_id, $shipment->country_code, $shipment->postal_code, $shipment->city)
                ->filter(fn (array $row): bool => $canViewCosts || $row['agent_id'] === null)->values(),
            'agents' => ShippingAgent::query()->where('workspace_id', $shipment->workspace_id)->active()->orderBy('name')->get(),
            'carrierAccounts' => CarrierAccount::query()->where('workspace_id', $shipment->workspace_id)->active()->orderBy('name')->get(),
            'siblings' => Shipment::query()->where('order_id', $shipment->order_id)->whereKeyNot($shipment->id)->orderBy('id')->get(),
            'variance' => $this->shipments->variance($shipment),
            'auditEntries' => $user->can('shipping.audit.view') ? $shipment->auditEntries()->with('user')->limit(30)->get() : collect(),
            'canViewCosts' => $canViewCosts,
            'canManage' => $user->can('shipping.shipments.manage'),
            'canBook' => $user->can('shipping.bookings.create'),
            'canVoid' => $user->can('shipping.bookings.void'),
            'bookingBlockers' => $user->can('shipping.bookings.create') ? $bookings->blockers($shipment) : [],
            'countries' => $this->countries(),
            'storeCurrency' => $shipment->currency,
        ]);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->shipments->update($shipment, $request->validated(), $request->user());

        return back()->with('success', __('Shipment details saved.'));
    }

    public function cartons(CartonsRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->shipments->syncCartons($shipment, $request->cartons(), $request->user());

        return back()->with('success', __('Cartons saved and billable weight recalculated.'));
    }

    public function booking(BookingRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->shipments->recordBooking($shipment, $request->validated(), $request->user());

        return back()->with('success', __('Tracking details saved.'));
    }

    public function status(ShipmentStatusRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->shipments->setStatus($shipment, $request->validated('status'), $request->user());

        return back()->with('success', __('Shipment status updated.'));
    }
}
