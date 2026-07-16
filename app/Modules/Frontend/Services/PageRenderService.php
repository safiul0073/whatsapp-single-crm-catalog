<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Faqs\Services\FaqsService;
use App\Modules\Frontend\Models\Page;

class PageRenderService
{
    public function __construct(
        protected ThemeRenderService $themeRender,
        protected ThemeRegistry $themes,
        protected MenuRenderService $menus,
        protected FaqsService $faqs,
        protected FrontendTranslationService $translations,
    ) {}

    public function payload(Page $page, string $themeKey): array
    {
        $page->loadMissing(['sections', 'pageSections']);

        $resolvedSections = [];

        foreach ($page->sections as $section) {
            $resolvedSections[] = array_merge(
                $this->themeRender->sectionView($themeKey, $section),
                ['section' => $this->translations->translateSection($section)]
            );
        }

        return [
            'themeKey' => $themeKey,
            'theme' => $this->themes->get($themeKey),
            'themeVars' => $this->themeRender->themeVariables($themeKey),
            'layoutView' => $this->themeRender->layoutView($themeKey, $page->default_layout),
            'page' => $page,
            'resolvedMenus' => $this->menus->resolveForTheme($themeKey),
            'headerServiceCards' => [],
            'headerProjectCategoryCards' => [],
            'headerProjectCategoryLinks' => [],
            'faqs' => $this->faqs->publishedFaqs(),
            'testimonials' => collect(),
            'serviceCategories' => collect(),
            'techStackCategories' => collect(),

            'resolvedSections' => $resolvedSections,
        ];
    }
}
