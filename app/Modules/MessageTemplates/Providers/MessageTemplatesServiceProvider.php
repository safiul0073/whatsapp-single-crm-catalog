<?php

namespace App\Modules\MessageTemplates\Providers;

use App\Modules\MessageTemplates\Services\MessageTemplateAiGeneratorService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class MessageTemplatesServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(MessageTemplateAiGeneratorService::class);
    }
}
