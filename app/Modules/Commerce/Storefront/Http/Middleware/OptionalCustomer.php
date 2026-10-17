<?php

namespace App\Modules\Commerce\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptionalCustomer
{
    public function handle(Request $r, Closure $next): Response
    {
        return $r->bearerToken() ? app(AuthenticateCustomer::class)->handle($r, $next) : $next($r);
    }
}
