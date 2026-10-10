<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAuthSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.manage') ?? false;
    }

    public function rules(): array
    {
        return ['enabled' => ['required', 'boolean'], 'channel_id' => ['nullable', 'required_if:enabled,1', 'integer'], 'authentication_template_id' => ['nullable', 'required_if:enabled,1', 'integer'], 'welcome_enabled' => ['nullable', 'boolean'], 'welcome_template_id' => ['nullable', 'required_if:welcome_enabled,1', 'integer']];
    }

    public function messages(): array
    {
        return ['authentication_template_id.required_if' => 'Choose an approved copy-code authentication template.', 'welcome_template_id.required_if' => 'Choose an approved account welcome template.'];
    }
}
