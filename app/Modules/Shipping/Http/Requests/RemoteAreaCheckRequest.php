<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoteAreaCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country' => filled($this->input('country')) ? strtoupper((string) $this->input('country')) : null]);
    }

    public function rules(): array
    {
        return [
            'country' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80'],
        ];
    }
}
