<?php

namespace App\Modules\MessageTemplates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_account_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
