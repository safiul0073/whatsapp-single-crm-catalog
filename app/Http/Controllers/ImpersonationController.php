<?php

namespace App\Http\Controllers;

use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(
        protected ImpersonationService $impersonationService,
    ) {}

    /**
     * Stop impersonating and return to original admin account.
     */
    public function stop(Request $request): RedirectResponse
    {
        if (! $this->impersonationService->isImpersonating($request)) {
            return redirect('/');
        }

        $this->impersonationService->stop($request);

        return redirect()->route('admin.dashboard')
            ->with('success', __('Impersonation ended. Welcome back!'));
    }
}
