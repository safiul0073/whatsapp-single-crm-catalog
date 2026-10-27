<?php

namespace App\Modules\TikTok\Providers;

use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\TikTok\Services\TikTokClient;
use App\Modules\TikTok\Services\TikTokDriver;
use App\Modules\TikTok\Services\TikTokSettingsService;
use App\Modules\TikTok\Services\TikTokTokenService;

class TikTokServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(TikTokSettingsService::class);
        $this->app->singleton(TikTokClient::class);
        $this->app->singleton(TikTokTokenService::class);
    }

    protected function bootModule(array $module): void
    {
        app(ChannelManager::class)->register(app(TikTokDriver::class));
    }
}
