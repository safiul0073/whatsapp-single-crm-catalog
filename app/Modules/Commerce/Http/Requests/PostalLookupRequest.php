<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Services\PostalCodeLookupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostalLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace') || ($this->user()?->can('commerce.pos.use') ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'country' => ['required', 'string', Rule::in(PostalCodeLookupService::countries())],
            'postal_code' => ['required', 'string', 'max:12'],
        ];
    }
}
