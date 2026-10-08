<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductShowcaseEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'shipping_method_id' => ['nullable', 'integer', 'min:1'],
            'groups' => ['required', 'array', 'min:1', 'max:50'],
            'groups.*' => ['array:mode,variant_id,quantity,color_id,box_count'],
            'groups.*.mode' => ['required', 'in:retail,wholesale'],
            'groups.*.variant_id' => ['required_if:groups.*.mode,retail', 'integer', 'min:1'],
            'groups.*.quantity' => ['required_if:groups.*.mode,retail', 'integer', 'min:1', 'max:10000'],
            'groups.*.color_id' => ['required_if:groups.*.mode,wholesale', 'integer', 'min:1'],
            'groups.*.box_count' => ['required_if:groups.*.mode,wholesale', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'country.required' => 'Select a delivery country.',
            'country.regex' => 'Select a valid delivery country.',
            'groups.required' => 'Select at least one quantity or box.',
            'groups.*.array' => 'Only product selections may be submitted.',
            'groups.*.quantity.min' => 'Select a positive whole quantity.',
            'groups.*.box_count.min' => 'Select a positive whole number of boxes.',
        ];
    }
}
