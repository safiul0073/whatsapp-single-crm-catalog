<?php

namespace App\Modules\Chatbots\Providers;

use App\Modules\Chatbots\Services\ChatbotPersonaGeneratorService;
use App\Modules\Chatbots\Services\ChatbotService;
use App\Modules\Chatbots\Services\ChatbotWidgetService;
use App\Modules\Chatbots\Services\ClaudeReplyService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class ChatbotsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatbotPersonaGeneratorService::class);
        $this->app->singleton(ChatbotService::class);
        $this->app->singleton(ChatbotWidgetService::class);
        $this->app->singleton(ClaudeReplyService::class);
    }
}
