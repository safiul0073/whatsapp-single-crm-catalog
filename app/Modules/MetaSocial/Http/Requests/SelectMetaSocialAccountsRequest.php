<?php

namespace App\Modules\MetaSocial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectMetaSocialAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_ids' => ['required', 'array', 'min:1', 'max:50'],
            'account_ids.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_ids.required' => __('Select at least one account to connect.'),
        ];
    }
}
