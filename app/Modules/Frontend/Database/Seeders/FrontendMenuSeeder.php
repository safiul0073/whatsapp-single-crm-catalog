<?php

namespace App\Modules\Frontend\Database\Seeders;

use App\Modules\Frontend\Models\FrontendMenu;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\MenuService;
use App\Modules\Frontend\Services\ThemeSettingsService;
use Illuminate\Database\Seeder;

class FrontendMenuSeeder extends Seeder
{
    public function run(): void
    {
        /** @var MenuService $menus */
        $menus = app(MenuService::class);
        /** @var ThemeSettingsService $settings */
        $settings = app(ThemeSettingsService::class);

        $home = Page::query()->where('slug', 'home')->first();
        $about = Page::query()->where('slug', 'about')->first();
        $blogUrl = route('blog.index');

        if (! $home || ! $about) {
            return;
        }

        $header = FrontendMenu::query()->where('slug', 'primary-navigation')->first();
        $header = $header
            ? $menus->update($header, [
                'name' => 'Primary Navigation',
                'slug' => 'primary-navigation',
                'status' => 'published',
                'items_payload' => json_encode([
                    [
                        'temp_key' => 'home-link',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'Home',
                        'linkable_type' => Page::class,
                        'linkable_id' => $home->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'about-link',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'About',
                        'linkable_type' => Page::class,
                        'linkable_id' => $about->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'blog-link',
                        'depth' => 0,
                        'item_type' => 'external',
                        'label' => 'Blog',
                        'url' => $blogUrl,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'resources-group',
                        'depth' => 0,
                        'item_type' => 'group',
                        'label' => 'Resources',
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'docs-link',
                        'depth' => 1,
                        'item_type' => 'external',
                        'label' => 'Documentation',
                        'url' => 'https://example.com/docs',
                        'target' => '_blank',
                        'is_visible' => true,
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            : $menus->create([
                'name' => 'Primary Navigation',
                'slug' => 'primary-navigation',
                'status' => 'published',
                'items_payload' => json_encode([
                    [
                        'temp_key' => 'home-link',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'Home',
                        'linkable_type' => Page::class,
                        'linkable_id' => $home->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'about-link',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'About',
                        'linkable_type' => Page::class,
                        'linkable_id' => $about->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'blog-link',
                        'depth' => 0,
                        'item_type' => 'external',
                        'label' => 'Blog',
                        'url' => $blogUrl,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'resources-group',
                        'depth' => 0,
                        'item_type' => 'group',
                        'label' => 'Resources',
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'docs-link',
                        'depth' => 1,
                        'item_type' => 'external',
                        'label' => 'Documentation',
                        'url' => 'https://example.com/docs',
                        'target' => '_blank',
                        'is_visible' => true,
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $footer = FrontendMenu::query()->where('slug', 'footer-links')->first();
        $footer = $footer
            ? $menus->update($footer, [
                'name' => 'Footer Links',
                'slug' => 'footer-links',
                'status' => 'published',
                'items_payload' => json_encode([
                    [
                        'temp_key' => 'footer-home',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'Home',
                        'linkable_type' => Page::class,
                        'linkable_id' => $home->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'footer-about',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'About',
                        'linkable_type' => Page::class,
                        'linkable_id' => $about->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            : $menus->create([
                'name' => 'Footer Links',
                'slug' => 'footer-links',
                'status' => 'published',
                'items_payload' => json_encode([
                    [
                        'temp_key' => 'footer-home',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'Home',
                        'linkable_type' => Page::class,
                        'linkable_id' => $home->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'footer-about',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'About',
                        'linkable_type' => Page::class,
                        'linkable_id' => $about->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $mobile = FrontendMenu::query()->where('slug', 'mobile-navigation')->first();
        $mobile = $mobile
            ? $menus->update($mobile, [
                'name' => 'Mobile Navigation',
                'slug' => 'mobile-navigation',
                'status' => 'published',
                'items_payload' => json_encode([
                    [
                        'temp_key' => 'mobile-home',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'Home',
                        'linkable_type' => Page::class,
                        'linkable_id' => $home->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'mobile-about',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'About',
                        'linkable_type' => Page::class,
                        'linkable_id' => $about->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'mobile-blog',
                        'depth' => 0,
                        'item_type' => 'external',
                        'label' => 'Blog',
                        'url' => $blogUrl,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            : $menus->create([
                'name' => 'Mobile Navigation',
                'slug' => 'mobile-navigation',
                'status' => 'published',
                'items_payload' => json_encode([
                    [
                        'temp_key' => 'mobile-home',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'Home',
                        'linkable_type' => Page::class,
                        'linkable_id' => $home->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'mobile-about',
                        'depth' => 0,
                        'item_type' => 'internal',
                        'label' => 'About',
                        'linkable_type' => Page::class,
                        'linkable_id' => $about->id,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                    [
                        'temp_key' => 'mobile-blog',
                        'depth' => 0,
                        'item_type' => 'external',
                        'label' => 'Blog',
                        'url' => $blogUrl,
                        'target' => '_self',
                        'is_visible' => true,
                    ],
                ], JSON_THROW_ON_ERROR),
            ]);

        $settings->set('theme.classic.menu.header', (string) $header->id);
        $settings->set('theme.classic.menu.footer', (string) $footer->id);
        $settings->set('theme.classic.menu.mobile', (string) $mobile->id);
    }
}
