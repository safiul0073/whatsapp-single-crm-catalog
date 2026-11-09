<?php

namespace App\Modules\Commerce\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Storefront\Services\FeatureIconCatalog;
use Illuminate\Http\JsonResponse;

class FeatureIconController extends Controller
{
    public function index(FeatureIconCatalog $catalog): JsonResponse
    {
        return response()->json($catalog->allIcons())->setPublic()->setMaxAge(86400);
    }
}
