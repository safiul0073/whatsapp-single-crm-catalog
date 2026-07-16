<?php

namespace App\Modules\Sms\Providers;

use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\Sms\Services\SmsProvider;

class SmsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsProvider::class);
    }

    protected function bootModule(array $module): void
    {
        app(ChannelManager::class)->register(app(SmsProvider::class));
    }
}
