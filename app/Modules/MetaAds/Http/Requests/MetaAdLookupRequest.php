<?php

namespace App\Modules\MetaAds\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Loads Page posts to boost or creator posts for partnership ads.
 */
class MetaAdLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meta-ads.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page_id' => ['required', 'string', 'max:64'],
            'instagram_account_id' => ['nullable', 'string', 'max:64'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
