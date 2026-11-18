<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Services\Carriers\FedexRateClient;
use App\Rules\CountryCode;
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
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'booking_enabled' => $this->boolean('booking_enabled'),
            'exchange_rate' => filled($this->input('exchange_rate')) ? $this->input('exchange_rate') : '1',
            'origin_country_code' => filled($this->input('origin_country_code')) ? strtoupper((string) $this->input('origin_country_code')) : null,
        ]);
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
            'origin_name' => ['nullable', 'string', 'max:120'],
            'origin_address_line1' => ['nullable', 'string', 'max:255'],
            'origin_city' => ['nullable', 'string', 'max:80'],
            'origin_state' => ['nullable', 'string', 'max:80'],
            'origin_postal_code' => ['nullable', 'string', 'max:20'],
            'origin_country_code' => ['nullable', 'string', 'size:2', new CountryCode],
            'origin_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'options' => ['nullable', 'array'],
            'options.pickup_type' => ['nullable', Rule::in(array_keys(FedexRateClient::PICKUP_TYPES))],
            'options.goods_description' => ['nullable', 'string', 'max:100'],
            'options.product_group' => ['nullable', 'string', 'alpha_num', 'max:10'],
            'options.product_type' => ['nullable', 'string', 'alpha_num', 'max:10'],
            'options.payment_type' => ['nullable', 'string', 'alpha_num', 'max:5'],
            'is_active' => ['boolean'],
            'booking_enabled' => ['boolean'],
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
        $data['options'] = array_filter((array) ($data['options'] ?? []), fn ($value): bool => filled($value)) ?: null;
        $allowed = ['client_id', 'client_secret', 'username', 'password', 'account_pin', 'account_entity', 'account_country_code'];
        $submitted = array_filter(array_intersect_key((array) $this->validated('credentials', []), array_flip($allowed)), fn ($value): bool => filled($value));
        $data['credentials'] = $this->boolean('clear_credentials') ? null : (array_merge((array) $existing?->credentials, $submitted) ?: null);

        return $data;
    }
}
