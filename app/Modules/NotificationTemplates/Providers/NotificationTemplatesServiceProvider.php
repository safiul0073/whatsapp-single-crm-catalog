<?php

namespace App\Modules\NotificationTemplates\Providers;

use App\Events\UserAutoNotification;
use App\Modules\NotificationTemplates\Listeners\LogNotificationFailed;
use App\Modules\NotificationTemplates\Listeners\LogNotificationSent;
use App\Modules\NotificationTemplates\Listeners\UserAutoNotificationListener;
use App\Modules\NotificationTemplates\Services\NotificationDispatchService;
use App\Modules\NotificationTemplates\Services\NotificationLogService;
use App\Modules\NotificationTemplates\Services\NotificationRecipientResolver;
use App\Modules\NotificationTemplates\Services\NotificationTemplateService;
use App\Modules\NotificationTemplates\Services\TemplateRenderer;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

class NotificationTemplatesServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(TemplateRenderer::class);
        $this->app->singleton(NotificationTemplateService::class);
        $this->app->singleton(NotificationLogService::class);
        $this->app->singleton(NotificationRecipientResolver::class);
        $this->app->singleton(NotificationDispatchService::class);
    }

    protected function bootModule(array $module): void
    {
        Event::listen(NotificationSent::class, LogNotificationSent::class);
        Event::listen(NotificationFailed::class, LogNotificationFailed::class);
        Event::listen(UserAutoNotification::class, UserAutoNotificationListener::class);
    }
}
