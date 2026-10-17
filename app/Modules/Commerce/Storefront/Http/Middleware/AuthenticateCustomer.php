<?php

namespace App\Modules\Commerce\Storefront\Http\Middleware;

use App\Modules\Commerce\Storefront\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = PersonalAccessToken::findToken((string) $request->bearerToken());
        abort_unless($token && $token->expires_at && $token->expires_at->isFuture() && $token->can('storefront'), 401);
        $customer = $token->tokenable;
        abort_unless($customer instanceof Customer && $customer->workspace_id === $request->attributes->get('store_workspace')->id && $customer->verified_at && $customer->verified_phone === $customer->contact?->phone && ! $customer->contact?->blocked_at, 401);
        $customer->withAccessToken($token);
        $request->attributes->set('store_customer', $customer);

        return $next($request);
    }
}
