<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'in:draft,active,archived']];
    }
}
