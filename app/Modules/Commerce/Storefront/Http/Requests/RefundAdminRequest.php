<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use App\Modules\Commerce\Storefront\Models\CashinRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.refunds.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route()->getActionMethod() === 'saveSettings') {
            $this->merge(['refunds_enabled' => $this->boolean('refunds_enabled')]);
        }
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'saveSettings' => ['refunds_enabled' => ['boolean']],
            'transition' => [
                'status' => ['required', 'in:processing,completed,admin_rejected'],
                'admin_note' => ['nullable', 'required_if:status,admin_rejected', 'string', 'max:2000'],
                'payout_method' => ['nullable', 'required_if:status,completed', 'in:wallet,manual'],
                'payout_channel' => ['nullable', 'required_if:payout_method,manual', Rule::in(array_keys(CashinRequest::METHODS))],
                'payout_reference' => ['nullable', 'required_if:payout_method,manual', 'string', 'max:120'],
            ],
            default => [],
        };
    }
}
