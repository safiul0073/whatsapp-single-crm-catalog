<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectInvoiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.invoices.manage') ?? false;
    }

    public function rules(): array
    {
        return ['owner_note' => ['nullable', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return ['owner_note.max' => 'Keep the note under 1000 characters.'];
    }
}
