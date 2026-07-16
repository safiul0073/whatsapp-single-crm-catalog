<?php

namespace App\Modules\Threads\Providers;

use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\Threads\Services\ThreadsClient;
use App\Modules\Threads\Services\ThreadsProvider;

class ThreadsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(ThreadsClient::class);
        $this->app->singleton(ThreadsProvider::class);
    }

    protected function bootModule(array $module): void
    {
        app(ChannelManager::class)->register(app(ThreadsProvider::class));
    }
}
