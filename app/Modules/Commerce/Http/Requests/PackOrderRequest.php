<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PackOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['quantities' => ['required', 'array', 'max:1000'], 'quantities.*' => ['required', 'integer', 'min:0', 'max:10000']];
    }

    public function messages(): array
    {
        return ['quantities.required' => 'Choose pieces to put in the retail box.'];
    }
}
