<?php

namespace App\Modules\ContactMessages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['required', 'string', 'max:160'],
            'interest' => ['required', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ];
    }
}
