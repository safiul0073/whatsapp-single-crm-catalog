<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Http\Requests\BookShipmentRequest;
use App\Modules\Shipping\Http\Requests\VoidBookingRequest;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingController extends ShippingBaseController
{
    public function __construct(private BookingService $bookings) {}

    public function store(BookShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $booked = $this->bookings->book($shipment, $request->user(), $request->boolean('confirm'));

        return back()->with('success', __('Booked with :courier. Tracking number :tracking. The label is under Documents.', ['courier' => $booked->carrierLabel(), 'tracking' => $booked->tracking_number]));
    }

    public function requestVoid(VoidBookingRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->bookings->requestVoid($shipment, $request->user(), (string) $request->validated('reason'));

        return back()->with('success', __('Cancellation requested. The owner has been asked to approve it.'));
    }

    public function approveVoid(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->bookings->approveVoid($shipment, $request->user(), $request->boolean('already_cancelled'));

        return back()->with('success', __('Booking cancelled.'));
    }

    public function rejectVoid(Request $request, Shipment $shipment): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);
        $this->bookings->rejectVoid($shipment, $request->user());

        return back()->with('success', __('Cancellation request declined. The booking stays active.'));
    }
}
