<?php

namespace App\Modules\AuthApi\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AuthApi\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Modules\AuthApi\Services\ApiSessionService;
use App\Modules\Shared\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthenticatedUserController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApiSessionService $apiSessionService,
    ) {}

    #[OA\Get(path: '/api/v1/auth/me')]
    #[OA\Response(response: 200, description: 'Authenticated user details')]
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse([
            'user' => new AuthenticatedUserResource($request->user()->loadMissing('roles')),
        ]);
    }

    #[OA\Post(path: '/api/v1/auth/logout')]
    #[OA\Response(response: 200, description: 'Logout successful')]
    public function destroy(Request $request): JsonResponse
    {
        $this->apiSessionService->logout($request->user(), $request);

        return $this->successResponse([], 'Logged out successfully.');
    }
}
