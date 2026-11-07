<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Models\CustomInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCustomInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.invoices.manage') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(CustomInvoice::STATUSES)]];
    }

    public function messages(): array
    {
        return ['status.in' => 'Choose a valid invoice status.'];
    }
}
