<?php

namespace App\Modules\PlansSubscriptions\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'restart_period' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['plan_id.required' => __('Choose a plan.'), 'plan_id.exists' => __('The selected plan does not exist.')];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['restart_period' => $this->boolean('restart_period')]);
    }
}
