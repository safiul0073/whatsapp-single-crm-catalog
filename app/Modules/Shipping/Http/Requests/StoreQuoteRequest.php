<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\RemoteArea;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'regex:/^(agent:\d+|account:\d+|carrier:(ups|fedex|aramex))$/'],
            'service' => ['nullable', 'string', 'max:80'],
            'base_rate' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'billable_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'transit_days' => ['nullable', 'string', 'max:40'],
            'remote_status' => ['required', Rule::in(array_merge(array_keys(RemoteArea::STATUSES), [RemoteArea::UNKNOWN]))],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'charges' => ['nullable', 'array'],
            'charges.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    public function messages(): array
    {
        return ['provider.regex' => __('Choose a courier or a shipping agent.')];
    }

    /**
     * Manual quotations always start unverified: they are not a confirmed price yet.
     *
     * @return array<string, mixed>
     */
    public function quoteData(int $workspaceId): array
    {
        $data = $this->validated();
        [$type, $id] = explode(':', $data['provider'], 2);
        $agent = $type === 'agent' ? ShippingAgent::query()->where('workspace_id', $workspaceId)->findOrFail((int) $id) : null;
        $account = $type === 'account' ? CarrierAccount::query()->where('workspace_id', $workspaceId)->findOrFail((int) $id) : null;

        $charges = [];
        foreach ($data['charges'] ?? [] as $reason => $amount) {
            if (array_key_exists($reason, ShippingCharge::REASONS) && (float) $amount > 0) {
                $charges[] = ['reason' => $reason, 'amount' => $amount];
            }
        }

        return [
            'shipping_agent_id' => $agent?->id,
            'shipping_carrier_account_id' => $account?->id,
            'carrier' => $agent?->carrier ?? $account?->carrier ?? ($type === 'carrier' ? $id : null),
            'source' => $agent ? 'manual_whatsapp' : 'manual_direct',
            'status' => 'unverified',
            'service' => $data['service'] ?? null,
            'base_rate' => $data['base_rate'],
            'currency' => strtoupper($data['currency']),
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'billable_weight_kg' => $data['billable_weight_kg'] ?? null,
            'transit_days' => $data['transit_days'] ?? null,
            'remote_status' => $data['remote_status'],
            'valid_until' => filled($data['valid_until'] ?? null) ? Carbon::parse($data['valid_until'])->endOfDay() : now()->addDays((int) config('shipping_carriers.quote_validity_days', 7)),
            'notes' => $data['notes'] ?? null,
            'charges' => $charges,
        ];
    }
}
