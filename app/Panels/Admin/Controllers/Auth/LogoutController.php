<?php

namespace App\Panels\Admin\Controllers\Auth;

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
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            $this->loginActivityService->recordLogout('App\Models\Admin', $admin->id, $request);

            $this->auditLogService->logCustom('admin_logout', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
            ]);
        }

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }
}
