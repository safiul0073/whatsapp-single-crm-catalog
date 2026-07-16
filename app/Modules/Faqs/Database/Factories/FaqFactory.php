<?php

namespace App\Modules\Faqs\Database\Factories;

use App\Modules\Faqs\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'question' => sprintf('%s?', rtrim(fake()->sentence(7), '.')),
            'answer' => fake()->paragraphs(2, true),
            'sort_order' => fake()->numberBetween(0, 20),
            'active' => true,
            'status' => 'published',
            'published_at' => now(),
        ];
    }
}
