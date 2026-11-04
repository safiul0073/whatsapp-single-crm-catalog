<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\TrackPageViewRequest;
use App\Modules\Commerce\Storefront\Services\VisitorTrackingService;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    public function pageView(TrackPageViewRequest $request, VisitorTrackingService $tracking): Response
    {
        $tracking->record($request, $request->attributes->get('store_workspace')->id, $request->validated());

        return response()->noContent();
    }
}
