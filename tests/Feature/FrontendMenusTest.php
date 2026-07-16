<?php

use App\Modules\Faqs\Models\Faq;
use App\Modules\Frontend\Database\Seeders\FrontendMenuSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use App\Modules\Frontend\Models\FrontendMenu;
use App\Modules\Frontend\Services\MenuAssignmentService;
use App\Modules\Frontend\Services\MenuRenderService;
use App\Modules\Frontend\Services\MenuService;
use Database\Seeders\WaProLandingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('assigned published menus render in the classic theme layout', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        FrontendMenuSeeder::class,
    ]);

    $html = view('frontend.themes.classic.layouts.page', [
        'page' => (object) [
            'meta_title' => 'Menu Preview',
            'title' => 'Menu Preview',
            'meta_description' => 'Menu preview page.',
        ],
        'theme' => [
            'label' => 'Classic',
            'view_namespace' => 'frontend.themes.classic',
        ],
        'themeKey' => 'classic',
        'themeVars' => ['logo_text' => 'Classic'],
        'resolvedMenus' => app(MenuRenderService::class)->resolveForTheme('classic'),
        'resolvedSections' => [],
    ])->render();

    expect($html)
        ->toContain('Home')
        ->toContain('About')
        ->toContain('Blog')
        ->toContain('Resources')
        ->toContain('Documentation');
});

test('header renders dynamic menu items when resolved menus are provided', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        FrontendMenuSeeder::class,
    ]);

    $resolvedMenus = app(MenuRenderService::class)->resolveForTheme('classic');

    $html = view('frontend.themes.classic.navigation.header', [
        'theme' => ['label' => 'Classic'],
        'themeVars' => [
            'logo_text' => 'Classic',
            'show_auth_links' => false,
        ],
        'resolvedMenus' => $resolvedMenus,
    ])->render();

    expect($html)
        ->toContain('Home')
        ->toContain('About')
        ->toContain('Blog')
        ->toContain('href="'.route('blog.index').'"')
        ->toContain('Resources')
        ->toContain('Documentation')
        ->toContain('href="https://example.com/docs"')
        ->toContain('target="_blank"');
});

test('header falls back gracefully when no resolved menus are provided', function () {
    $html = view('frontend.themes.classic.navigation.header', [
        'theme' => ['label' => 'Classic'],
        'themeVars' => [
            'logo_text' => 'Classic',
            'show_auth_links' => true,
        ],
        'resolvedMenus' => [],
    ])->render();

    expect($html)
        ->toContain('Classic')
        ->toContain('Sign in')
        ->toContain('Sign up');
});

test('footer renders dynamic menu items split into Product and Company columns', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        FrontendMenuSeeder::class,
    ]);

    $resolvedMenus = app(MenuRenderService::class)->resolveForTheme('classic');

    $html = view('frontend.themes.classic.navigation.footer', [
        'theme' => ['label' => 'Classic'],
        'themeVars' => [
            'logo_text' => 'Classic',
            'footer_address' => 'Test Address',
            'footer_phone' => '+1 555-0000',
            'footer_email' => 'test@example.com',
            'footer_copyright' => '© 2026 Test',
        ],
        'resolvedMenus' => $resolvedMenus,
    ])->render();

    expect($html)
        ->toContain('Product')
        ->toContain('Company')
        ->toContain('Home')
        ->toContain('About')
        ->toContain('Test Address')
        ->toContain('+1 555-0000')
        ->toContain('test@example.com');
});

test('footer renders social icons only when URLs are configured', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        FrontendMenuSeeder::class,
    ]);

    $resolvedMenus = app(MenuRenderService::class)->resolveForTheme('classic');

    $html = view('frontend.themes.classic.navigation.footer', [
        'theme' => ['label' => 'Classic'],
        'themeVars' => [
            'logo_text' => 'Classic',
            'footer_social_facebook' => 'https://facebook.com/test',
            'footer_social_x' => 'https://x.com/test',
            'footer_copyright' => '© 2026 Test',
        ],
        'resolvedMenus' => $resolvedMenus,
    ])->render();

    expect($html)
        ->toContain('https://facebook.com/test')
        ->toContain('https://x.com/test')
        ->not->toContain('aria-label="Instagram"')
        ->not->toContain('aria-label="LinkedIn"');
});

test('homepage renders the static WaPro faq section', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        FrontendMenuSeeder::class,
        WaProLandingSeeder::class,
    ]);

    Faq::factory()->create([
        'question' => 'Can you join an existing product team?',
        'answer' => 'This CMS FAQ should not replace the WaPro landing page FAQ.',
        'sort_order' => 1,
        'status' => 'published',
        'active' => true,
    ]);

    Faq::factory()->create([
        'question' => 'Draft homepage FAQ?',
        'answer' => 'This should stay hidden.',
        'sort_order' => 2,
        'status' => 'draft',
        'active' => true,
        'published_at' => null,
    ]);

    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('Do I need a WhatsApp Business API account to start?');
    $response->assertSee('How fast are templates approved?');
    $response->assertDontSee('Can you join an existing product team?');
    $response->assertDontSee('Draft homepage FAQ?');
});

test('assigned menus cannot be deleted while still attached to theme slots', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        FrontendMenuSeeder::class,
    ]);

    $menu = FrontendMenu::query()->where('slug', 'primary-navigation')->firstOrFail();

    expect(fn () => app(MenuService::class)->delete($menu))
        ->toThrow(ValidationException::class);
});

test('footer assignments reject menus that exceed footer depth rules', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        FrontendMenuSeeder::class,
    ]);

    $menu = FrontendMenu::query()->where('slug', 'primary-navigation')->firstOrFail();

    expect(fn () => app(MenuAssignmentService::class)->validateForSlot('footer', $menu))
        ->toThrow(ValidationException::class);
});
