<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use App\Modules\Commerce\Storefront\Models\CashinRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_customer');
    }

    public function rules(): array
    {
        return $this->route()->getActionMethod() === 'store' ? [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'method' => ['required', Rule::in(array_keys(CashinRequest::METHODS))],
            'account_details' => ['required', 'string', 'min:5', 'max:500'],
        ] : [];
    }
}
