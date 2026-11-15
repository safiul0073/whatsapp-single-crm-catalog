<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use App\Modules\Commerce\Storefront\Models\RefundRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_customer');
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'store' => [
                'order' => ['required', 'string', 'max:100'],
                'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
                'reason' => ['required', Rule::in(RefundRequest::REASONS)],
                'details' => ['nullable', 'string', 'max:2000'],
            ],
            'decide' => [
                'decision' => ['required', 'in:approved,rejected'],
                'comment' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
            ],
            default => [],
        };
    }
}
