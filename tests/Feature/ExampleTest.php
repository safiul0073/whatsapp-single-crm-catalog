<?php

use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use Database\Seeders\WaProLandingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the homepage renders the WaPro WhatsApp marketing template without cms content', function () {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('WaPro');
    $response->assertSee('WhatsApp');
    $response->assertSee('campaigns, done');
    $response->assertSee('right.');
    $response->assertSee('href="'.url('/login').'"', false);
    $response->assertSee('href="'.url('/register').'"', false);
    $response->assertDontSee("Let's get started");
    $response->assertDontSee('Laravel has an incredibly rich ecosystem');
});

test('the WaPro marketing script keeps the template animation components wired', function () {
    $entry = file_get_contents(resource_path('js/wapro/home.js'));

    expect($entry)
        ->toContain('./components/hero-editorial.js')
        ->toContain('./components/auth-nav-links.js')
        ->toContain('./components/marquee.js')
        ->toContain('./components/spotlight.js')
        ->toContain('./components/reveal.js')
        ->toContain('./components/cta-parallax.js');
});
