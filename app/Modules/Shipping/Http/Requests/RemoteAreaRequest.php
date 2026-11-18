<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\RemoteArea;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Rules\CountryCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemoteAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country_code' => strtoupper((string) $this->input('country_code')), 'currency' => strtoupper((string) $this->input('currency'))]);
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'regex:/^(agent:\d+|carrier:(ups|fedex|aramex))$/'],
            'country_code' => ['required', 'string', 'size:2', new CountryCode],
            'postal_from' => ['nullable', 'string', 'max:20', 'required_without:city'],
            'postal_to' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80', 'required_without:postal_from'],
            'status' => ['required', Rule::in(array_keys(RemoteArea::STATUSES))],
            'fee' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'confirmed' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'postal_from.required_without' => __('Enter a postal code (or prefix) or a city.'),
            'city.required_without' => __('Enter a postal code (or prefix) or a city.'),
        ];
    }

    /**
     * An entry only counts once someone ticks that the provider confirmed it.
     *
     * @return array<string, mixed>
     */
    public function areaData(int $workspaceId): array
    {
        $data = $this->validated();
        [$type, $id] = explode(':', $data['provider'], 2);
        $agent = $type === 'agent' ? ShippingAgent::query()->where('workspace_id', $workspaceId)->findOrFail((int) $id) : null;
        $confirmed = (bool) ($data['confirmed'] ?? false);

        return [
            'shipping_agent_id' => $agent?->id,
            'carrier' => $agent ? $agent->carrier : $id,
            'country_code' => $data['country_code'],
            'postal_from' => $data['postal_from'] ?? null,
            'postal_to' => $data['postal_to'] ?? null,
            'city' => $data['city'] ?? null,
            'status' => $data['status'],
            'fee' => $data['fee'],
            'currency' => $data['currency'],
            'verified_by' => $confirmed ? $this->user()->id : null,
            'verified_at' => $confirmed ? now() : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
