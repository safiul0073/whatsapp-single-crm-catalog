<?php

namespace App\Modules\Newsletter\Database\Factories;

use App\Modules\Newsletter\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
