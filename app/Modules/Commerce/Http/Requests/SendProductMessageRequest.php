<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendProductMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['variant_id' => ['required', 'integer'], 'body' => ['nullable', 'string', 'max:1024']];
    }
}
