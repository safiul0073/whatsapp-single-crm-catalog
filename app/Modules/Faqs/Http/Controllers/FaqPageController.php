<?php

namespace App\Modules\Faqs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Faqs\Services\FaqsService;
use App\Modules\Frontend\Services\ActiveThemeResolver;
use App\Modules\Frontend\Services\MenuRenderService;
use App\Modules\Frontend\Services\ThemeRegistry;
use App\Modules\Frontend\Services\ThemeRenderService;
use Illuminate\Http\Response;

class FaqPageController extends Controller
{
    public function __construct(
        protected ActiveThemeResolver $activeThemeResolver,
        protected ThemeRegistry $themes,
        protected ThemeRenderService $themeRender,
        protected MenuRenderService $menus,
        protected FaqsService $faqs
    ) {}

    public function index(): Response
    {
        $themeKey = $this->activeThemeResolver->resolve();

        return response()->view('faqs::web.index', [
            'themeKey' => $themeKey,
            'theme' => $this->themes->get($themeKey),
            'themeVars' => $this->themeRender->themeVariables($themeKey),
            'resolvedMenus' => $this->menus->resolveForTheme($themeKey),
            'headerServiceCards' => [],
            'headerProjectCategoryCards' => [],
            'headerProjectCategoryLinks' => [],
            'resolvedSections' => [],
            'faqs' => $this->faqs->publishedFaqs(),
        ]);
    }
}
