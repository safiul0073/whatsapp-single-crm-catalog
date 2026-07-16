<?php

namespace Database\Factories;

use App\Modules\Crm\Models\CrmPipeline;
use App\Modules\Crm\Models\CrmStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmStageFactory extends Factory
{
    protected $model = CrmStage::class;

    public function definition(): array
    {
        $pipeline = CrmPipeline::query()->inRandomOrder()->first();

        return ['workspace_id' => $pipeline?->workspace_id, 'pipeline_id' => $pipeline?->id, 'name' => fake()->unique()->word(), 'position' => fake()->numberBetween(0, 10), 'color' => fake()->hexColor()];
    }
}
