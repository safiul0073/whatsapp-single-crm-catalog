<?php

namespace App\Modules\Email\Providers;

use App\Modules\Email\Services\EmailGatewayInterface;
use App\Modules\Email\Services\EmailProvider;
use App\Modules\Email\Services\LaravelMailGateway;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class EmailServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailGatewayInterface::class, LaravelMailGateway::class);
        $this->app->singleton(EmailProvider::class);
    }

    protected function bootModule(array $module): void
    {
        app(ChannelManager::class)->register(app(EmailProvider::class));
    }
}
