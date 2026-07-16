<?php

namespace App\Modules\Frontend\Services;

use App\Modules\Frontend\Models\FrontendMenu;
use App\Modules\Frontend\Models\FrontendMenuItem;
use App\Modules\Frontend\Models\Page;
use Illuminate\Support\Facades\Cache;

class MenuRenderService
{
    protected string $trackerKey = 'frontend_menu_render_cache_keys';

    protected int $cacheTtl = 3600;

    public function __construct(
        protected ThemeRegistry $themes,
        protected ThemeSettingsService $settings,
        protected MenuSlotRegistry $slots,
        protected FrontendTranslationService $translations
    ) {}

    public function resolveForTheme(string $themeKey): array
    {
        $menus = [];

        foreach ($this->slots->all() as $slotKey => $slot) {
            $menus[$slotKey] = $this->resolveSlot($themeKey, $slotKey);
        }

        return $menus;
    }

    public function resolveSlot(string $themeKey, string $slotKey): ?array
    {
        $cacheKey = "frontend_menu_render.{$themeKey}.{$slotKey}.".app()->getLocale();
        $this->trackCacheKey($cacheKey);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($themeKey, $slotKey) {
            $menuId = $this->settings->getThemeSetting($themeKey, "menu.{$slotKey}");

            if (! $menuId) {
                return null;
            }

            $menu = FrontendMenu::query()
                ->published()
                ->with(['items.linkable'])
                ->find($menuId);

            if (! $menu) {
                return null;
            }

            return [
                'menu' => $menu,
                'slot' => $this->slots->get($slotKey),
                'items' => $this->buildTree($menu),
            ];
        });
    }

    public function clearCache(): void
    {
        $keys = Cache::get($this->trackerKey, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget($this->trackerKey);
    }

    protected function buildTree(FrontendMenu $menu): array
    {
        $items = $menu->items->sortBy('sort_order')->values();
        $roots = [];
        $childrenMap = [];

        foreach ($items as $item) {
            if ($item->parent_id) {
                $childrenMap[$item->parent_id][] = $item;

                continue;
            }

            $roots[] = $item;
        }

        return array_map(function (FrontendMenuItem $item) use ($childrenMap) {
            return $this->mapRenderItem($item, $childrenMap[$item->id] ?? []);
        }, $roots);
    }

    protected function mapRenderItem(FrontendMenuItem $item, array $children = []): array
    {
        $url = null;

        if ($item->item_type === 'internal' && $item->linkable instanceof Page) {
            $url = $item->linkable->is_home
                ? route('home')
                : route('frontend.page', ['slug' => $item->linkable->slug]);
        }

        if ($item->item_type === 'external') {
            $url = $item->url;
        }

        return [
            'id' => $item->id,
            'label' => $this->translations->translateText($item->label, 'label'),
            'type' => $item->item_type,
            'url' => $url,
            'target' => $item->target ?: '_self',
            'is_visible' => (bool) $item->is_visible,
            'children' => array_map(fn (FrontendMenuItem $child) => $this->mapRenderItem($child), $children),
        ];
    }

    protected function trackCacheKey(string $cacheKey): void
    {
        $keys = Cache::get($this->trackerKey, []);

        if (! in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::put($this->trackerKey, $keys, $this->cacheTtl);
        }
    }
}
