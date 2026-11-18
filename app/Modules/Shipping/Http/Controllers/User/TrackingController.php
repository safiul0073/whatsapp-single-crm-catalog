<?php

namespace App\Modules\Shipping\Http\Controllers\User;

use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\Carriers\CarrierApiException;
use App\Modules\Shipping\Services\TrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrackingController extends ShippingBaseController
{
    public function refresh(Request $request, Shipment $shipment, TrackingService $tracking): RedirectResponse
    {
        $this->assertWorkspace($request, $shipment);

        try {
            $result = $tracking->sync($shipment);
        } catch (CarrierApiException $exception) {
            return back()->with('error', __('Tracking could not be updated: :reason', ['reason' => $exception->summary()]));
        }

        return back()->with('success', $result['new_events'] > 0
            ? __(':count new tracking update(s). Status: :status.', ['count' => $result['new_events'], 'status' => __(Shipment::STATUSES[$result['status']] ?? $result['status'])])
            : __('Tracking checked. No new updates from the courier.'));
    }
}
