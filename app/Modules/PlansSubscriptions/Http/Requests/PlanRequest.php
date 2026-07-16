<?php

namespace App\Modules\PlansSubscriptions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('plans', 'slug')->ignore($planId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'interval' => ['required', Rule::in(['month', 'year', 'lifetime'])],
            'messages_per_month' => ['nullable', 'integer', 'min:0'],
            'contacts' => ['nullable', 'integer', 'min:0'],
            'whatsapp_numbers' => ['nullable', 'integer', 'min:0'],
            'ai_tokens' => ['nullable', 'integer', 'min:0'],
            'campaigns_per_month' => ['nullable', 'integer', 'min:0'],
            'chatbots' => ['nullable', 'integer', 'min:0'],
            'team_members' => ['nullable', 'integer', 'min:0'],
            'max_lead_generations_per_month' => ['nullable', 'integer', 'min:0'],
            'max_ai_lead_results_per_month' => ['nullable', 'integer', 'min:0'],
            'max_ai_credits' => ['nullable', 'integer', 'min:0'],
            'automation_ai_builder' => ['nullable', 'boolean'],
            'campaign_ai_doctor' => ['nullable', 'boolean'],
            'features_text' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'automation_ai_builder' => $this->boolean('automation_ai_builder'),
            'campaign_ai_doctor' => $this->boolean('campaign_ai_doctor'),
            'sort_order' => $this->input('sort_order', 0),
        ]);
    }
}
