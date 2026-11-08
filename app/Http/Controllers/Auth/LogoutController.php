<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\LoginActivity\Services\LoginActivityService;
use App\Modules\Shared\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LogoutController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected LoginActivityService $loginActivityService,
        protected SessionService $sessionService,
    ) {}

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        if ($user) {
            $user->setRememberToken(Str::random(60));
            $user->save();

            $revoked = $this->sessionService->revokeAllForUser($user->id);

            $this->loginActivityService->recordLogout('App\Models\User', $user->id, $request);

            $this->auditLogService->logCustom('logout', [
                'user_id' => $user->id,
                'email' => $user->email,
                'sessions_revoked' => $revoked,
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out successfully.');
    }
}
