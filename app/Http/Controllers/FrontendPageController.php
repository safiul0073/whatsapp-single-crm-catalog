<?php

namespace App\Http\Controllers;

use App\Modules\Frontend\Services\ActiveThemeResolver;
use App\Modules\Frontend\Services\FrontendPageService;
use App\Modules\Frontend\Services\PageRenderService;
use App\Modules\Frontend\Services\ThemeRenderService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FrontendPageController extends Controller
{
    public function __construct(
        protected FrontendPageService $pages,
        protected ActiveThemeResolver $activeThemeResolver,
        protected PageRenderService $renderer,
        protected ThemeRenderService $themeRender
    ) {}

    public function home()
    {
        if (! Schema::hasTable('pages')) {
            abort(404);
        }

        try {
            $page = $this->pages->homePage();
        } catch (Throwable) {
            abort(404);
        }

        abort_if(! $page, 404);

        try {
            $payload = $this->renderer->payload($page, $this->activeThemeResolver->resolve());
        } catch (Throwable) {
            abort(404);
        }

        return response()->view($payload['layoutView'], $payload);
    }

    public function show(string $slug)
    {
        try {
            if (! Schema::hasTable('pages')) {
                abort(404);
            }

            $page = $this->pages->findBySlug($slug);
        } catch (Throwable) {
            abort(404);
        }

        abort_if(! $page, 404);

        $payload = $this->renderer->payload($page, $this->activeThemeResolver->resolve());

        return response()->view($payload['layoutView'], $payload);
    }

    public function themeCss(string $theme): Response
    {
        $vars = $this->themeRender->themeVariables($theme);
        $primary = $this->cssColor($vars['primary_color'] ?? '', '#111827');
        $accent = $this->cssColor($vars['accent_color'] ?? '', '#1f2937');

        $css = <<<CSS
:root {
    --color-primary: {$primary};
    --color-primary-hover: color-mix(in srgb, {$primary} 85%, #000);
    --color-brand-blue: {$primary};
    --color-brand-blue-electric: {$primary};
    --color-info: {$primary};
    --color-brand-navy-ink: {$accent};
    --color-brand-navy: {$accent};
    --color-brand-navy-deep: {$accent};
    --color-text-strong: {$accent};
}
CSS;

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    protected function cssColor(string $value, string $fallback): string
    {
        $value = trim($value);

        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1) {
            return $value;
        }

        return $fallback;
    }
}
