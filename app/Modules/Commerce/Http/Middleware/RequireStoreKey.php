<?php

namespace App\Modules\Commerce\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStoreKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('commerce.store_key');
        abort_if($expected === '', 503, 'Store integration is not configured.');
        abort_unless(hash_equals($expected, (string) $request->header('X-Store-Key')), 403);

        return $next($request);
    }
}
