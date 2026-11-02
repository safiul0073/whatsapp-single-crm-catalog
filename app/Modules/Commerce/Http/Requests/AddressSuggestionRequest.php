<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Rules\CountryCode;
use Illuminate\Foundation\Http\FormRequest;

class AddressSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.pos.use') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:3', 'max:120'], 'country' => ['nullable', new CountryCode]];
    }
}
