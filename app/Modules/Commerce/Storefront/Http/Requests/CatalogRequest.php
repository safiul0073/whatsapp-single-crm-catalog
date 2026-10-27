<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:150'],
            'color' => ['nullable', 'string', 'max:80'],
            'size' => ['nullable', 'string', 'max:80'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'attr' => ['nullable', 'array', 'max:10'],
            'attr.*' => ['nullable', 'string', 'max:120'],
            'option' => ['nullable', 'array', 'max:10'],
            'option.*' => ['nullable', 'string', 'max:120'],
            'in_stock' => ['nullable', 'boolean'],
            'selling_mode' => ['nullable', 'in:retail,wholesale,all'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,name_asc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ];
    }
}
