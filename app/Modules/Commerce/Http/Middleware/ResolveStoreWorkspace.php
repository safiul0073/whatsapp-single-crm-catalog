<?php

namespace App\Modules\Commerce\Http\Middleware;

use App\Modules\Workspaces\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The storefront API is intentionally unauthenticated and single-store: requests are served by
 * COMMERCE_STORE_WORKSPACE_ID when set, otherwise by the oldest active workspace.
 */
class ResolveStoreWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $storeWorkspace = Workspace::query()
            ->where('status', 'active')
            ->when(config('commerce.store_workspace_id'), fn ($query, $workspaceId) => $query->whereKey($workspaceId))
            ->oldest('id')
            ->first();
        abort_unless($storeWorkspace, 503, 'No active store workspace is configured.');
        $request->attributes->set('store_workspace', $storeWorkspace);

        return $next($request);
    }
}
