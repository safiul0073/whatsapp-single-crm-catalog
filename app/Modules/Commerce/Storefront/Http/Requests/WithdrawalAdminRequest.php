<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use App\Modules\Commerce\Storefront\Models\CashinRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawalAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.referrals.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route()->getActionMethod() === 'saveSchedule') {
            $this->merge(['cashin_enabled' => $this->boolean('cashin_enabled'), 'cashin_methods' => array_values((array) $this->input('cashin_methods', []))]);
        }
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'saveSchedule' => [
                'cashin_enabled' => ['boolean'],
                'withdrawal_frequency' => ['required', 'in:weekly,monthly'],
                'withdrawal_weekly_day' => ['required', 'integer', 'min:0', 'max:6'],
                'withdrawal_monthly_day' => ['required', 'integer', 'min:1', 'max:31'],
                'withdrawal_opens_at' => ['required', 'date_format:H:i'],
                'withdrawal_closes_at' => ['required', 'date_format:H:i', 'after:withdrawal_opens_at'],
                'min_cashin_balance' => ['required', 'numeric', 'min:0', 'max:9999999999'],
                'max_cashin_amount' => ['nullable', 'numeric', 'gte:min_cashin_balance', 'max:9999999999'],
                'cashin_methods' => ['required', 'array', 'min:1'],
                'cashin_methods.*' => [Rule::in(array_keys(CashinRequest::METHODS))],
            ],
            'update' => [
                'status' => ['required', 'in:approved,processing,rejected,paid'],
                'admin_note' => ['nullable', 'string', 'max:255'],
                'payout_reference' => ['nullable', 'string', 'max:120'],
            ],
            default => [],
        };
    }
}
