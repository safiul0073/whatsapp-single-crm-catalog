<?php

use App\Models\Admin;
use App\Modules\Blogs\Database\Seeders\BlogsSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Frontend\Models\Page;
use App\Modules\Frontend\Services\ThemeRegistry;
use App\Modules\Frontend\Services\ThemeSettingsService;
use Database\Seeders\WaProLandingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('theme registry resolves installed themes', function () {
    $registry = app(ThemeRegistry::class);

    expect(array_keys($registry->all()))
        ->toContain('classic')
        ->and($registry->defaultThemeKey())->toBe('classic');
});

test('seeded frontend theme settings default to classic', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
    ]);

    expect(config('frontend-themes.classic.view_namespace'))->toBe('frontend.themes.classic')
        ->and(app(ThemeSettingsService::class)->activeTheme())->toBe('classic');
});

test('frontend theme settings page hides internal theme contract details', function () {
    $admin = Admin::factory()->create();
    Permission::findOrCreate('frontend-themes.view', 'admin');
    $admin->givePermissionTo('frontend-themes.view');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.frontend-themes.index'))
        ->assertOk()
        ->assertDontSee('Theme Contract')
        ->assertDontSee('Supported sections')
        ->assertDontSee('Layouts');
});

test('published home route renders the WaPro marketing homepage', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
        FrontendSectionSeeder::class,
        FrontendPageSeeder::class,
        BlogsSeeder::class,
        WaProLandingSeeder::class,
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('WhatsApp');
    $response->assertSee('campaigns, done');
    $response->assertSee('WaPro');
    $response->assertDontSee("Let's get started");
});

test('unsupported section types use the fallback renderer publicly', function () {
    $this->seed([
        FrontendThemeSettingSeeder::class,
    ]);

    $page = Page::create([
        'title' => 'Fallback Demo',
        'slug' => 'fallback-demo',
        'status' => 'published',
        'default_layout' => 'default',
        'is_home' => false,
        'is_system' => false,
        'published_at' => now(),
    ]);

    $section = FrontendSection::create([
        'name' => 'Legacy Banner',
        'slug' => 'legacy-banner',
        'type' => 'legacy_banner',
        'status' => 'published',
        'data' => [],
        'theme_overrides' => [],
    ]);

    $page->pageSections()->create([
        'frontend_section_id' => $section->id,
        'sort_order' => 0,
    ]);

    $response = $this->get('/fallback-demo');

    $response->assertOk();
    $response->assertSee('This section is not supported by the current theme.');
});
