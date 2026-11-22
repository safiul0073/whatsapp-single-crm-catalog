<?php

namespace App\Modules\Commerce\Storefront\Http\Middleware;

use App\Modules\Commerce\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetStorefrontLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->header('X-Locale') ?: $request->query('locale');
        $locale = is_string($requested) ? strtolower(substr($requested, 0, 2)) : null;

        if (! in_array($locale, Product::STOREFRONT_LOCALES, true)) {
            $locale = $request->getPreferredLanguage(Product::STOREFRONT_LOCALES);
        }

        app()->setLocale($locale ?: config('app.locale'));

        return $next($request);
    }
}
