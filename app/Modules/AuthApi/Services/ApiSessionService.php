<?php

namespace App\Modules\AuthApi\Services;

use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\LoginActivity\Services\LoginActivityService;
use Illuminate\Http\Request;

class ApiSessionService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected LoginActivityService $loginActivityService,
    ) {}

    public function createToken(User $user, Request $request, string $deviceName, string $action = 'login.api'): string
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->loginActivityService->recordLogin(User::class, $user->id, $request);

        $this->auditLogService->logCustom($action, [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user->createToken($deviceName)->plainTextToken;
    }

    public function logout(User $user, Request $request): void
    {
        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        $this->loginActivityService->recordLogout(User::class, $user->id, $request);

        $this->auditLogService->logCustom('logout.api', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
