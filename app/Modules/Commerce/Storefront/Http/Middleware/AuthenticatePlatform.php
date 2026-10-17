<?php

namespace App\Modules\Commerce\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePlatform
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('storefront.platform_secret');
        abort_unless(strlen($secret) >= 32 && hash_equals($secret, (string) $request->header('X-Storefront-Secret')), 401);

        return $next($request);
    }
}
