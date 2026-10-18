<?php

namespace App\Modules\Commerce\Storefront\Http\Middleware;

use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePlatform
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Storefront-Secret');
        if (strlen($provided) < 32) {
            abort(401);
        }

        $envSecret = (string) config('storefront.platform_secret');
        if (strlen($envSecret) >= 32 && hash_equals($envSecret, $provided)) {
            return $next($request);
        }

        $matched = false;
        foreach (StorefrontSetting::query()->pluck('config') as $config) {
            $storedSecret = (string) ($config['deployment']['platform_secret'] ?? '');
            if (strlen($storedSecret) >= 32 && hash_equals($storedSecret, $provided)) {
                $matched = true;
                break;
            }
        }

        abort_unless($matched, 401);

        return $next($request);
    }
}
