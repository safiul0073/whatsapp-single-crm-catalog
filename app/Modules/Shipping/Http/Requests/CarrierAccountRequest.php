<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\CarrierAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarrierAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'carrier' => ['required', Rule::in(array_keys(CarrierAccount::CARRIERS))],
            'account_number' => ['nullable', 'string', 'max:60'],
            'environment' => ['required', Rule::in(CarrierAccount::ENVIRONMENTS)],
            'dim_divisor' => ['required', 'integer', 'min:1000', 'max:10000'],
            'weight_rounding_step' => ['required', 'numeric', 'gt:0', 'max:5'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:500'],
            'clear_credentials' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Credential fields are write-only: a blank field keeps the stored value.
     *
     * @return array<string, mixed>
     */
    public function accountData(?CarrierAccount $existing = null): array
    {
        $data = collect($this->validated())->except(['credentials', 'clear_credentials'])->all();
        $allowed = ['client_id', 'client_secret', 'username', 'password', 'account_pin', 'account_entity', 'account_country_code'];
        $submitted = array_filter(array_intersect_key((array) $this->validated('credentials', []), array_flip($allowed)), fn ($value): bool => filled($value));
        $data['credentials'] = $this->boolean('clear_credentials') ? null : (array_merge((array) $existing?->credentials, $submitted) ?: null);

        return $data;
    }
}
