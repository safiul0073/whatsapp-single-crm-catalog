<?php

namespace App\Modules\AuthApi\Http\Controllers\Api\V1\Auth;

use App\Enums\NotificationTemplateSlug;
use App\Events\UserAutoNotification;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\AuthApi\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Modules\AuthApi\Http\Resources\Api\V1\Auth\AuthenticatedUserResource;
use App\Modules\AuthApi\Services\ApiSessionService;
use App\Modules\Shared\Traits\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;

class RegistrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApiSessionService $apiSessionService,
        protected AuditLogService $auditLogService,
    ) {}

    #[OA\Post(path: '/api/v1/auth/register')]
    #[OA\Response(response: 201, description: 'Registration successful')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(RegisterRequest $request): JsonResponse
    {
        Role::findOrCreate('user', 'web');

        $user = User::query()->create([
            'first_name' => $request->string('first_name')->value(),
            'last_name' => $request->string('last_name')->value(),
            'email' => $request->string('email')->lower()->value(),
            'phone' => $request->input('phone'),
            'password' => $request->string('password')->value(),
            'is_active' => true,
        ]);

        $user->syncRoles(['user']);

        event(new Registered($user));
        event(new UserAutoNotification($user, NotificationTemplateSlug::WELCOME));

        $this->auditLogService->logCustom('register.api', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $token = $this->apiSessionService->createToken(
            $user,
            $request,
            $request->input('device_name', $request->userAgent() ?: 'api-device'),
            'login.api.register',
        );

        return $this->createdResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new AuthenticatedUserResource($user->loadMissing('roles')),
        ], 'Registration successful.');
    }
}
