<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommerceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['cart_enabled' => ['required', 'boolean'], 'catalog_visible' => ['required', 'boolean']];
    }
}
