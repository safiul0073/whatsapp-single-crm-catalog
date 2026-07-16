<?php

namespace Database\Factories;

use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\CrmLead;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmActivityFactory extends Factory
{
    protected $model = CrmActivity::class;

    public function definition(): array
    {
        $lead = CrmLead::query()->inRandomOrder()->first();

        return ['workspace_id' => $lead?->workspace_id, 'lead_id' => $lead?->id, 'contact_id' => $lead?->contact_id, 'conversation_id' => $lead?->conversation_id, 'type' => 'note', 'title' => fake()->sentence(3), 'description' => fake()->sentence()];
    }
}
