<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\PostalLookupRequest;
use App\Modules\Commerce\Services\PostalCodeLookupService;
use Illuminate\Http\JsonResponse;

class GeoController extends Controller
{
    public function postalLookup(PostalLookupRequest $request, PostalCodeLookupService $postal): JsonResponse
    {
        return response()->json(['data' => $postal->lookup($request->validated('country'), $request->validated('postal_code'))]);
    }
}
