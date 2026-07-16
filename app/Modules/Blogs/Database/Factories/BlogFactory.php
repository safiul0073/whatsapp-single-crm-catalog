<?php

namespace App\Modules\Blogs\Database\Factories;

use App\Modules\Blogs\Models\Blog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Blog>
 */
class BlogFactory extends Factory
{
    protected $model = Blog::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'blog_category_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'author_name' => fake()->name(),
            'excerpt' => fake()->paragraph(),
            'content' => fake()->paragraphs(5, true),
            'featured_image' => null,
            'featured_image_media_id' => null,
            'read_time_minutes' => fake()->numberBetween(3, 9),
            'sort_order' => fake()->numberBetween(1, 99),
            'active' => true,
            'status' => 'published',
            'meta_title' => null,
            'meta_description' => null,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
