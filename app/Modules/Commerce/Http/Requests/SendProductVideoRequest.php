<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendProductVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['product_media_id' => ['required', 'integer'], 'caption' => ['nullable', 'string', 'max:1024']];
    }
}
