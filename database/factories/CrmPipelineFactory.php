<?php

namespace Database\Factories;

use App\Modules\Crm\Models\CrmPipeline;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmPipelineFactory extends Factory
{
    protected $model = CrmPipeline::class;

    public function definition(): array
    {
        return ['workspace_id' => Workspace::query()->inRandomOrder()->value('id'), 'name' => fake()->unique()->words(2, true), 'is_default' => false];
    }
}
