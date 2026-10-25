<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReferralAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('commerce.referrals.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route()->getActionMethod() === 'saveSettings') {
            $this->merge([
                'enabled' => $this->boolean('enabled'),
            ]);
        }
        if (in_array($this->route()->getActionMethod(), ['saveSlab', 'saveRule'], true)) {
            $this->merge(['active' => $this->boolean('active')]);
        }
    }

    public function rules(): array
    {
        $money = ['required', 'numeric', 'min:0', 'max:9999999999'];

        return match ($this->route()->getActionMethod()) {
            'saveSettings' => [
                'enabled' => ['boolean'],
                'reward_trigger' => ['required', 'in:signup,delivered_order'],
                'reward_scope' => ['required', 'in:first_order,every_order'],
                'reward_type' => ['required', 'in:fixed,percent,percent_capped'],
                'reward_value' => $this->input('reward_type') === 'fixed' ? $money : ['required', 'numeric', 'min:0', 'max:100'],
                'reward_cap' => ['nullable', 'required_if:reward_type,percent_capped', 'numeric', 'min:0', 'max:9999999999'],
                'min_order_amount' => $money,
                'referee_discount_type' => ['required', 'in:none,fixed,percent'],
                'referee_discount_value' => $this->input('referee_discount_type') === 'percent' ? ['required', 'numeric', 'min:0', 'max:100'] : $money,
                'referee_min_order' => $money,
                'max_redeem_percent' => ['required', 'integer', 'min:0', 'max:100'],
                'min_spend_balance' => $money,
                'max_referrals_per_customer' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'terms' => ['nullable', 'string', 'max:5000'],
            ],
            'saveSlab' => [
                'name' => ['required', 'string', 'max:120'],
                'period' => ['required', 'in:daily,weekly,monthly'],
                'min_referrals' => ['required', 'integer', 'min:1', 'max:100000'],
                'reward_amount' => $money,
                'active' => ['boolean'],
            ],
            'saveRule' => [
                'customer_id' => ['required', 'integer'],
                'reward_type' => ['required', 'in:fixed,percent,percent_capped'],
                'reward_value' => $this->input('reward_type') === 'fixed' ? $money : ['required', 'numeric', 'min:0', 'max:100'],
                'reward_cap' => ['nullable', 'required_if:reward_type,percent_capped', 'numeric', 'min:0', 'max:9999999999'],
                'min_order_amount' => $money,
                'notes' => ['nullable', 'string', 'max:255'],
                'active' => ['boolean'],
            ],
            'adjustWallet' => [
                'customer_id' => ['required', 'integer'],
                'direction' => ['required', 'in:credit,debit'],
                'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
                'note' => ['required', 'string', 'max:255'],
            ],
            'updateCashin' => [
                'status' => ['required', 'in:approved,rejected,paid'],
                'admin_note' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }
}
