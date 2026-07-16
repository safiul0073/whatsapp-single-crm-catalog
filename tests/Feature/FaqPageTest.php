<?php

use App\Modules\Faqs\Models\Faq;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the faq page with active published faq items in order', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);

    Faq::factory()->create([
        'question' => 'First visible question?',
        'answer' => 'First visible answer.',
        'sort_order' => 1,
        'status' => 'published',
        'active' => true,
    ]);

    Faq::factory()->create([
        'question' => 'Draft question?',
        'answer' => 'Should not render.',
        'sort_order' => 2,
        'status' => 'draft',
        'active' => true,
        'published_at' => null,
    ]);

    Faq::factory()->create([
        'question' => 'Second visible question?',
        'answer' => 'Second visible answer.',
        'sort_order' => 2,
        'status' => 'published',
        'active' => true,
    ]);

    Faq::factory()->create([
        'question' => 'Archived question?',
        'answer' => 'Should not render.',
        'sort_order' => 3,
        'status' => 'archived',
        'active' => true,
    ]);

    Faq::factory()->create([
        'question' => 'Inactive question?',
        'answer' => 'Should not render.',
        'sort_order' => 4,
        'status' => 'published',
        'active' => false,
    ]);

    $response = $this->get(route('faq.index'));

    $response->assertSuccessful();
    $response->assertSee('Frequently Asked Questions');
    $response->assertSeeInOrder(['First visible question?', 'Second visible question?']);
    $response->assertDontSee('Draft question?');
    $response->assertDontSee('Archived question?');
    $response->assertDontSee('Inactive question?');
});

it('serves faq before the catch-all frontend page slug route', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);

    $this->get('/faq')->assertSuccessful();
});
