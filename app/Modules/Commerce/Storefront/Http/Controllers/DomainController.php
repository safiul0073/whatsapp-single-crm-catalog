<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Storefront\Services\DomainResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function resolve(Request $r, DomainResolver $resolver): JsonResponse
    {
        return response()->json(['data' => $resolver->resolve((string) $r->query('host'))]);
    }

    public function allowed(Request $r, DomainResolver $resolver): JsonResponse
    {
        $resolver->resolve((string) $r->query('host'));

        return response()->json(['allowed' => true]);
    }
}
