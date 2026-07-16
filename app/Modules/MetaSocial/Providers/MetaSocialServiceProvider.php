<?php

namespace App\Modules\MetaSocial\Providers;

use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MetaSocial\Services\MetaSocialClient;
use App\Modules\MetaSocial\Services\MetaSocialDriver;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class MetaSocialServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(MetaSocialClient::class);
    }

    protected function bootModule(array $module): void
    {
        $manager = app(ChannelManager::class);

        $manager->register(new MetaSocialDriver(app(MetaSocialClient::class), 'messenger'));
        $manager->register(new MetaSocialDriver(app(MetaSocialClient::class), 'instagram'));
    }
}
