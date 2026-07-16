<?php

test('classic pages use the theme-local vite entrypoint and public brand assets', function () {
    $html = view('frontend.themes.classic.layouts.page', [
        'page' => (object) [
            'meta_title' => 'Classic Demo',
            'title' => 'Classic Demo',
            'meta_description' => 'Classic theme asset wiring test.',
        ],
        'theme' => [
            'label' => 'Classic',
            'view_namespace' => 'frontend.themes.classic',
        ],
        'themeKey' => 'classic',
        'themeVars' => [
            'logo_text' => 'Classic',
        ],
        'resolvedMenus' => [],
        'resolvedSections' => [],
    ])->render();

    expect($html)
        ->toContain('/vendor/phosphor/regular/style.css')
        ->and(str_contains($html, '/build/assets/') || str_contains($html, ':5173/resources/'))->toBeTrue()
        ->and($html)->not->toContain('resources/js/frontend-classic.js')
        ->toContain('/assets/brand/favicon.png')
        ->toContain('Classic')
        ->not->toContain('href="index.html"');
});
