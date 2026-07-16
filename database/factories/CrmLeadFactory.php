<?php

namespace Database\Factories;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Models\CrmStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrmLeadFactory extends Factory
{
    protected $model = CrmLead::class;

    public function definition(): array
    {
        $stage = CrmStage::query()->with('pipeline')->inRandomOrder()->first();
        $contact = Contact::query()->where('workspace_id', $stage?->workspace_id)->inRandomOrder()->first();

        return ['workspace_id' => $stage?->workspace_id, 'contact_id' => $contact?->id, 'pipeline_id' => $stage?->pipeline_id, 'stage_id' => $stage?->id, 'title' => fake()->sentence(3), 'source' => 'manual', 'status' => 'open'];
    }
}
