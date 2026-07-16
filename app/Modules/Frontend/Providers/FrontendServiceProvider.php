<?php

namespace App\Modules\Frontend\Providers;

use App\Modules\Frontend\Services\ActiveThemeResolver;
use App\Modules\Frontend\Services\FrontendPageService;
use App\Modules\Frontend\Services\FrontendSectionService;
use App\Modules\Frontend\Services\MenuAssignmentService;
use App\Modules\Frontend\Services\MenuRenderService;
use App\Modules\Frontend\Services\MenuService;
use App\Modules\Frontend\Services\MenuSlotRegistry;
use App\Modules\Frontend\Services\MenuTreeService;
use App\Modules\Frontend\Services\PageComposerService;
use App\Modules\Frontend\Services\PageRenderService;
use App\Modules\Frontend\Services\SectionRegistry;
use App\Modules\Frontend\Services\ThemeRegistry;
use App\Modules\Frontend\Services\ThemeRenderService;
use App\Modules\Frontend\Services\ThemeSettingsService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class FrontendServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeRegistry::class);
        $this->app->singleton(ThemeSettingsService::class);
        $this->app->singleton(ActiveThemeResolver::class);
        $this->app->singleton(SectionRegistry::class);
        $this->app->singleton(FrontendSectionService::class);
        $this->app->singleton(PageComposerService::class);
        $this->app->singleton(MenuSlotRegistry::class);
        $this->app->singleton(MenuTreeService::class);
        $this->app->singleton(MenuAssignmentService::class);
        $this->app->singleton(MenuRenderService::class);
        $this->app->singleton(MenuService::class);
        $this->app->singleton(FrontendPageService::class);
        $this->app->singleton(ThemeRenderService::class);
        $this->app->singleton(PageRenderService::class);
    }
}
