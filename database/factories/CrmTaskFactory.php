<?php

namespace Database\Factories;

use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Models\CrmTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmTaskFactory extends Factory
{
    protected $model = CrmTask::class;

    public function definition(): array
    {
        $lead = CrmLead::query()->inRandomOrder()->first();

        return ['workspace_id' => $lead?->workspace_id, 'lead_id' => $lead?->id, 'contact_id' => $lead?->contact_id, 'assigned_to' => $lead?->assigned_to, 'title' => fake()->sentence(3), 'status' => 'pending', 'priority' => 'normal', 'due_at' => now()->addDay()];
    }
}
