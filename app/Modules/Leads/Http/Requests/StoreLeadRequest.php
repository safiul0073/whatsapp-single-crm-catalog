<?php

namespace App\Modules\Leads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:255'],
            'place' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'stage' => ['nullable', 'string', 'in:new,contacted,qualified,converted,won,lost'],
            'source' => ['nullable', 'string', 'max:80'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_without' => 'Add a phone number or email before saving this lead.',
            'email.required_without' => 'Add a phone number or email before saving this lead.',
        ];
    }
}
