<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\LoginActivity\Services\LoginActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected LoginActivityService $loginActivityService,
    ) {}

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        if ($user) {
            $this->loginActivityService->recordLogout('App\Models\User', $user->id, $request);

            $this->auditLogService->logCustom('logout', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out successfully.');
    }
}
