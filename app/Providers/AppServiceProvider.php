<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\User;
use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Services\WidgetRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WidgetRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super admin bypasses all permission checks
        Gate::before(function ($user, $ability) {
            return $user instanceof Admin && $user->hasRole('super-admin') ? true : null;
        });

        Gate::before(fn ($user, $ability): ?bool => $user instanceof User ? true : null);

        View::composer('components.layouts.partials.user-sidebar', function ($view) {
            $user = Auth::guard('web')->user();
            $unreadCount = 0;

            if ($user) {
                $unreadCount = app(SystemNotificationService::class)->getUnreadCount($user);
            }

            $view->with('sidebarUnreadCount', $unreadCount);
        });

        View::composer('components.navigation.topbar', function ($view) {
            $panelKey = app('current.panel')['key'] ?? null;
            $unreadCount = 0;

            if ($panelKey === 'admin') {
                $admin = Auth::guard('admin')->user();
                if ($admin) {
                    $unreadCount = app(SystemNotificationService::class)->getUnreadCount($admin);
                }
            } elseif ($panelKey) {
                $user = Auth::guard('web')->user();
                if ($user) {
                    $unreadCount = app(SystemNotificationService::class)->getUnreadCount($user);
                }
            }

            $view->with('topbarUnreadCount', $unreadCount);
        });

        View::composer('layouts.auth', function ($view) {
            $view->with('authTestimonials', collect());
            $view->with('authLoginSection', FrontendSection::where('type', 'auth_login')->where('status', 'published')->first());
            $view->with('authRegisterSection', FrontendSection::where('type', 'auth_register')->where('status', 'published')->first());
        });

        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });
    }
}
