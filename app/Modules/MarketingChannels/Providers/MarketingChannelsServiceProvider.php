<?php

namespace App\Modules\MarketingChannels\Providers;

use App\Modules\MarketingChannels\Services\ChannelAccountSetupService;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class MarketingChannelsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelManager::class);
        $this->app->singleton(WorkspaceResolver::class);
        $this->app->singleton(ChannelAccountSetupService::class);
    }
}
