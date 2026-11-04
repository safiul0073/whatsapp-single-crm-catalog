<?php

namespace App\Modules\MetaSocial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConnectMetaBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('Meta did not return a login code. Please try connecting again.'),
        ];
    }
}
