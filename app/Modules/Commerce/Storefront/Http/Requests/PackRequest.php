<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    public function rules(): array
    {
        return ['replace_group_id' => ['nullable', 'integer', 'min:1'], 'selection' => ['required', 'array:type,variant_id,quantity,product_id,colors,template_id,pack_count,items']];
    }
}
