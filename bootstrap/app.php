<?php

use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ShareImpersonation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

$webMiddleware = [
    SetLocale::class,
    ShareImpersonation::class,
];

$trustedProxyHeaders = Request::HEADER_X_FORWARDED_FOR
    | Request::HEADER_X_FORWARDED_HOST
    | Request::HEADER_X_FORWARDED_PORT
    | Request::HEADER_X_FORWARDED_PROTO;

$csrfExcept = [
    'webhooks/channels/*',
    'widgets/chatbot/*',
];

$middlewareAliases = [
    'panel' => PanelAccess::class,
    'role' => RoleMiddleware::class,
    'permission' => PermissionMiddleware::class,
    '2fa' => EnsureTwoFactorAuthenticated::class,
];

$isAdminRequest = static fn (Request $request): bool => $request->is('admin')
    || $request->is('admin/*');

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use (
        $csrfExcept,
        $isAdminRequest,
        $middlewareAliases,
        $trustedProxyHeaders,
        $webMiddleware
    ): void {
        $middleware->web(append: $webMiddleware);

        $middleware->trustProxies(
            at: '*',
            headers: $trustedProxyHeaders
        );

        $middleware->validateCsrfTokens(except: $csrfExcept);
        $middleware->alias($middlewareAliases);

        $middleware->redirectUsersTo(function (Request $request) use ($isAdminRequest): string {
            return $isAdminRequest($request)
                ? route('admin.dashboard')
                : route('user.dashboard');
        });

        $middleware->redirectGuestsTo(function (Request $request) use ($isAdminRequest): string {
            return $isAdminRequest($request)
                ? route('admin.login')
                : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$app->useLangPath(resource_path('lang'));

return $app;
