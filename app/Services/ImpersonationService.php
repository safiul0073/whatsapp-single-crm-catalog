<?php

namespace App\Services;

use App\Models\Admin;
use App\Modules\LoginActivity\Services\LoginActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationService
{
    public function __construct(
        protected LoginActivityService $loginActivityService,
    ) {}

    /**
     * Start impersonating another admin user.
     */
    public function start(Admin $impersonator, Admin $target, Request $request): void
    {
        $request->session()->put('impersonating_from_id', $impersonator->id);
        $request->session()->put('impersonating_from_name', $impersonator->name);
        $request->session()->put('impersonating_from_guard', 'admin');

        // Log the impersonation start
        $this->loginActivityService->recordImpersonateStart(
            Admin::class,
            $impersonator->id,
            $request,
            [
                'impersonated_user_id' => $target->id,
                'impersonated_user_name' => $target->name,
                'impersonated_user_email' => $target->email,
            ],
        );

        // Login as the target admin
        Auth::guard('admin')->login($target);
    }

    /**
     * Stop impersonating and return to original admin.
     */
    public function stop(Request $request): void
    {
        $adminId = $request->session()->get('impersonating_from_id');
        $impersonatedUser = Auth::guard('admin')->user();

        // Log the impersonation end
        if ($adminId) {
            $this->loginActivityService->recordImpersonateStop(
                Admin::class,
                $adminId,
                $request,
                [
                    'impersonated_user_id' => $impersonatedUser?->id,
                    'impersonated_user_name' => $impersonatedUser?->name,
                ],
            );
        }

        // Restore original admin
        if ($adminId) {
            $admin = Admin::find($adminId);
            if ($admin) {
                Auth::guard('admin')->login($admin);
            }
        }

        // Clean up session
        $request->session()->forget([
            'impersonating_from_id',
            'impersonating_from_name',
            'impersonating_from_guard',
        ]);
    }

    /**
     * Check if the current request is an impersonation session.
     */
    public function isImpersonating(Request $request): bool
    {
        return $request->session()->has('impersonating_from_id');
    }

    /**
     * Get the original admin's info.
     *
     * @return array{id: int, name: string, guard: string}|null
     */
    public function getImpersonator(Request $request): ?array
    {
        if (! $this->isImpersonating($request)) {
            return null;
        }

        return [
            'id' => $request->session()->get('impersonating_from_id'),
            'name' => $request->session()->get('impersonating_from_name'),
            'guard' => $request->session()->get('impersonating_from_guard'),
        ];
    }
}
