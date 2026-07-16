<?php

namespace App\Modules\Telegram\Providers;

use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\Telegram\Services\TelegramBotProvider;
use App\Modules\Telegram\Services\TelegramInviteService;
use App\Modules\Telegram\Services\TelegramOptInService;

class TelegramServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(TelegramBotProvider::class);
        $this->app->singleton(TelegramOptInService::class);
        $this->app->singleton(TelegramInviteService::class);
    }

    protected function bootModule(array $module): void
    {
        app(ChannelManager::class)->register(app(TelegramBotProvider::class));
    }
}
