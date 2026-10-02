<?php

namespace App\Modules\Commerce\Http\Middleware;

use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Workspaces\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStoreIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) >= 40 && strlen($token) <= 256, 401);
        $setting = StoreOrderSetting::query()->where('integration_token_hash', hash('sha256', $token))->first();
        abort_unless($setting, 401);
        $request->attributes->set('store_workspace', Workspace::query()->where('status', 'active')->findOrFail($setting->workspace_id));

        return $next($request);
    }
}
