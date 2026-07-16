<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->randomNumber(4)),
            'status' => WorkspaceStatus::Active,
            'timezone' => fake()->timezone(),
            'settings' => [],
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkspaceStatus::Suspended,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WorkspaceStatus::Archived,
        ]);
    }
}
