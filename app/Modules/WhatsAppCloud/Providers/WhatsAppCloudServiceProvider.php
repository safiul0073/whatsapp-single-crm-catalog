<?php

namespace App\Modules\WhatsAppCloud\Providers;

use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudClient;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;

class WhatsAppCloudServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppCloudClient::class);
        $this->app->singleton(WhatsAppCloudDriver::class);
    }

    protected function bootModule(array $module): void
    {
        app(ChannelManager::class)->register(app(WhatsAppCloudDriver::class));
    }
}
