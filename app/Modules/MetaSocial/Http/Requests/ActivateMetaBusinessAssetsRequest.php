<?php

namespace App\Modules\MetaSocial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateMetaBusinessAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'messenger' => ['nullable', 'array', 'max:100'],
            'messenger.*' => ['string', 'max:255'],
            'instagram' => ['nullable', 'array', 'max:100'],
            'instagram.*' => ['string', 'max:255'],
            'ad_accounts' => ['nullable', 'array', 'max:100'],
            'ad_accounts.*' => ['string', 'max:255'],
            'catalogs' => ['nullable', 'array', 'max:100'],
            'catalogs.*' => ['string', 'max:255'],
        ];
    }
}
