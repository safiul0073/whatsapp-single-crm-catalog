<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['channel_account_id' => ['required', 'integer'], 'meta_catalog_id' => ['required', 'string', 'max:120'], 'sync_mode' => ['required', 'in:feed,api'], 'is_active' => ['nullable', 'boolean']];
    }
}
