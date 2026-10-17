<?php

namespace App\Modules\Commerce\Storefront\Http\Middleware;

use App\Modules\Commerce\Storefront\Services\DomainResolver;
use App\Modules\Workspaces\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = app(DomainResolver::class)->resolve((string) $request->header('X-Store-Host'));
        $request->attributes->set('store_workspace', Workspace::query()->findOrFail($store['store_id']));
        $request->attributes->set('store_domain', $store);

        return $next($request);
    }
}
