<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendProductListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['variant_ids' => ['required', 'array', 'min:1', 'max:30'], 'variant_ids.*' => ['required', 'integer', 'distinct'], 'header' => ['nullable', 'string', 'max:60'], 'body' => ['nullable', 'string', 'max:1024']];
    }
}
