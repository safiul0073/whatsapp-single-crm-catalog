<?php

namespace App\Modules\Commerce\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\WhatsAppCustomerAuthRequest;
use App\Modules\Commerce\Services\WhatsAppCustomerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppCustomerAuthController extends Controller
{
    public function status(Request $request, WhatsAppCustomerAuthService $auth): JsonResponse
    {
        return response()->json(['available' => $auth->available($request->attributes->get('store_workspace')->id)]);
    }

    public function challenge(WhatsAppCustomerAuthRequest $request, WhatsAppCustomerAuthService $auth): JsonResponse
    {
        return response()->json($auth->challenge($request->attributes->get('store_workspace')->id, $request->validated()));
    }

    public function verify(WhatsAppCustomerAuthRequest $request, string $challenge, WhatsAppCustomerAuthService $auth): JsonResponse
    {
        return response()->json($auth->verify($request->attributes->get('store_workspace')->id, $challenge, $request->validated()));
    }

    public function register(WhatsAppCustomerAuthRequest $request, WhatsAppCustomerAuthService $auth): JsonResponse
    {
        return response()->json($auth->register($request->attributes->get('store_workspace')->id, $request->validated()));
    }
}
