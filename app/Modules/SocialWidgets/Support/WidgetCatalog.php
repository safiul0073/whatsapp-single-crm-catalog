<?php

namespace App\Modules\SocialWidgets\Support;

use Illuminate\Support\Facades\Route;

class WidgetCatalog
{
    /**
     * Category order and blurbs follow Poper's library; only categories with a working widget render.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'essentials' => 'Consent, accessibility, announcements — the site-wide basics.',
            'utilities' => 'Chat buttons, booking, search, currency — handy tools.',
            'content-blocks' => 'FAQs, timelines, pricing, comparisons, trust badges.',
            'media-showcase' => 'Carousels, galleries, logo walls, testimonials in motion.',
            'reviews' => 'Star ratings from Google, Trustpilot and more.',
            'social-feeds' => 'Live content from Instagram and other social channels.',
        ];
    }

    /**
     * @return array<int, array{key: string, name: string, category: string, icon: string, tone: string, description: string, keywords: string, preview: string, url: string, recommended: bool}>
     */
    public static function widgets(): array
    {
        return array_values(array_filter([
            [
                'key' => 'instagram-feed',
                'name' => 'Instagram Feed',
                'category' => 'social-feeds',
                'icon' => 'ph-instagram-logo',
                'tone' => 'instagram',
                'description' => 'Show your latest Instagram posts in 11 layouts.',
                'keywords' => 'instagram feed social posts gallery grid',
                'preview' => 'instagram-feed',
                'url' => Route::has('user.social-widgets.layouts') ? route('user.social-widgets.layouts', 'instagram') : null,
                'recommended' => true,
            ],
        ], fn (array $widget): bool => $widget['url'] !== null));
    }

    /**
     * @return array<string, array{label: string, blurb: string, widgets: array<int, array<string, mixed>>}>
     */
    public static function sections(): array
    {
        $widgets = collect(self::widgets())->groupBy('category');

        return collect(self::categories())
            ->filter(fn (string $blurb, string $key): bool => $widgets->has($key))
            ->map(fn (string $blurb, string $key): array => [
                'label' => str($key)->replace('-', ' ')->replace('media showcase', 'Media & Showcase')->ucfirst()->toString(),
                'blurb' => $blurb,
                'widgets' => $widgets->get($key)->all(),
            ])
            ->all();
    }
}
