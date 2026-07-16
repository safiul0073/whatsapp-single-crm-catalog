<?php

namespace App\Modules\Languages\Http\Requests;

use App\Modules\Languages\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function rules(): array
    {
        $language = $this->route('language');
        $languageId = $language instanceof Language ? $language->getKey() : $language;

        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('languages', 'code')->ignore($languageId)],
            'name' => 'required|string|max:255',
            'native_name' => 'required|string|max:255',
            'direction' => 'required|in:ltr,rtl',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
