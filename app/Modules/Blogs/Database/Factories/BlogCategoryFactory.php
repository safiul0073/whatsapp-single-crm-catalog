<?php

namespace App\Modules\Blogs\Database\Factories;

use App\Modules\Blogs\Models\BlogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => str($name)->headline()->toString(),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 99),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
