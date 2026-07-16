<?php

use App\Modules\Faqs\Database\Seeders\FaqsSeeder;
use App\Modules\Faqs\Models\Faq;
use App\Modules\Shared\Support\ModuleRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('registers the faqs module and routes', function (): void {
    $module = app(ModuleRegistry::class)->find('faqs');

    expect($module)->not->toBeNull();
    expect(Route::has('admin.faqs.index'))->toBeTrue();
    expect(Route::has('faq.index'))->toBeTrue();
});

it('creates and updates faqs', function (): void {
    $admin = createAdminUser();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->post(route('admin.faqs.store'), [
        'question' => 'How long does discovery usually take?',
        'answer' => 'Most discovery engagements run for one to two weeks.',
        'sort_order' => 2,
        'status' => 'published',
        'active' => 1,
    ])->assertRedirect(route('admin.faqs.index'));

    $faq = Faq::query()->where('question', 'How long does discovery usually take?')->firstOrFail();

    expect($faq->published_at)->not->toBeNull();
    expect($faq->active)->toBeTrue();

    $this->put(route('admin.faqs.update', $faq), [
        'question' => 'How long does planning usually take?',
        'answer' => 'Planning usually lands within the first sprint.',
        'sort_order' => 1,
        'status' => 'draft',
        'active' => 0,
    ])->assertRedirect(route('admin.faqs.index'));

    $faq->refresh();

    expect($faq->question)->toBe('How long does planning usually take?');
    expect($faq->status)->toBe('draft');
    expect($faq->published_at)->toBeNull();
    expect($faq->active)->toBeFalse();
});

it('validates faq admin requests', function (): void {
    $admin = createAdminUser();

    $this->withoutMiddleware()
        ->actingAs($admin, 'admin');

    $this->from(route('admin.faqs.create'))
        ->post(route('admin.faqs.store'), [
            'question' => '',
            'answer' => '',
            'sort_order' => -1,
            'status' => 'invalid',
        ])
        ->assertRedirect(route('admin.faqs.create'))
        ->assertSessionHasErrors(['question', 'answer', 'sort_order', 'status']);
});

it('includes faq seeders in the main database seeder', function (): void {
    $seeder = new class extends DatabaseSeeder
    {
        /** @var array<int, string> */
        public array $calledSeeders = [];

        /**
         * @param  array<int, class-string<Seeder>>|class-string<Seeder>  $class
         */
        public function call($class, $silent = false, array $parameters = []): static
        {
            $this->calledSeeders = is_array($class) ? $class : [$class];

            return $this;
        }
    };

    $seeder->run();

    expect($seeder->calledSeeders)->toContain(FaqsSeeder::class);
});

it('seeds faqs', function (): void {
    $this->seed(FaqsSeeder::class);

    expect(Faq::query()->count())->toBe(6);

    $faq = Faq::query()->where('question', 'How do you structure a new project kickoff?')->firstOrFail();

    expect($faq->status)->toBe('published');
    expect($faq->published_at)->not->toBeNull();
});
