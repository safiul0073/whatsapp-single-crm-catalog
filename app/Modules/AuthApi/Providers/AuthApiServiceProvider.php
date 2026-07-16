<?php

namespace App\Modules\AuthApi\Providers;

use App\Modules\AuthApi\Services\AuthChallengeService;
use App\Modules\AuthApi\Services\OtpDeliveryService;
use App\Modules\AuthApi\Services\SocialAccountService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class AuthApiServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthChallengeService::class);
        $this->app->singleton(OtpDeliveryService::class);
        $this->app->singleton(SocialAccountService::class);
    }
}
