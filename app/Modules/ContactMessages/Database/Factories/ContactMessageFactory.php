<?php

namespace App\Modules\ContactMessages\Database\Factories;

use App\Modules\ContactMessages\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'company' => fake()->company(),
            'interest' => fake()->randomElement(['Demo', 'Pricing', 'Support']),
            'message' => fake()->paragraph(),
            'status' => ContactMessage::STATUS_NEW,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Feature test',
            'source_url' => fake()->url(),
        ];
    }
}
