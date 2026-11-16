<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\RemoteArea;
use App\Modules\Shipping\Models\ShippingAgent;
use Illuminate\Support\Collection;

/**
 * Remote-area status is always answered per provider. A destination is only called
 * remote when a confirmed entry for that provider says so; otherwise it stays unknown.
 */
class RemoteAreaService
{
    /**
     * @return array{status: string, label: string, fee: string, currency: ?string, area_id: ?int}
     */
    public function statusFor(int $workspaceId, ?string $countryCode, ?string $postalCode, ?string $city, ?string $carrier = null, ?int $agentId = null): array
    {
        $unknown = ['status' => RemoteArea::UNKNOWN, 'label' => RemoteArea::statusLabel(RemoteArea::UNKNOWN), 'fee' => '0', 'currency' => null, 'area_id' => null];

        if (blank($countryCode) || (blank($postalCode) && blank($city))) {
            return $unknown;
        }

        $match = RemoteArea::query()
            ->where('workspace_id', $workspaceId)
            ->where('country_code', strtoupper($countryCode))
            ->whereNotNull('verified_at')
            ->when($agentId, fn ($query) => $query->where('shipping_agent_id', $agentId), fn ($query) => $query->whereNull('shipping_agent_id')->where('carrier', $carrier))
            ->get()
            ->filter(fn (RemoteArea $area): bool => $this->matches($area, $postalCode, $city))
            ->sortByDesc(fn (RemoteArea $area): int => ['normal' => 1, 'remote' => 2, 'extended_remote' => 3][$area->status] ?? 0)
            ->first();

        if (! $match) {
            return $unknown;
        }

        return [
            'status' => $match->status,
            'label' => RemoteArea::statusLabel($match->status),
            'fee' => $match->status === 'normal' ? '0' : (string) $match->fee,
            'currency' => $match->currency,
            'area_id' => $match->id,
        ];
    }

    /**
     * One row per direct courier and per active agent for the given destination.
     *
     * @return Collection<int, array{provider: string, carrier: ?string, agent_id: ?int, status: string, label: string, fee: string, currency: ?string}>
     */
    public function statusForAllProviders(int $workspaceId, ?string $countryCode, ?string $postalCode, ?string $city): Collection
    {
        $rows = collect(CarrierAccount::CARRIERS)->map(fn (string $label, string $carrier): array => [
            'provider' => $label.' Direct',
            'carrier' => $carrier,
            'agent_id' => null,
        ] + $this->statusFor($workspaceId, $countryCode, $postalCode, $city, $carrier))->values();

        $agents = ShippingAgent::query()->where('workspace_id', $workspaceId)->active()->orderBy('name')->get()
            ->map(fn (ShippingAgent $agent): array => [
                'provider' => $agent->name.($agent->carrierLabel() ? ' ('.$agent->carrierLabel().')' : ''),
                'carrier' => $agent->carrier,
                'agent_id' => $agent->id,
            ] + $this->statusFor($workspaceId, $countryCode, $postalCode, $city, $agent->carrier, $agent->id));

        return $rows->concat($agents)->values();
    }

    private function matches(RemoteArea $area, ?string $postalCode, ?string $city): bool
    {
        $hasPostalRule = filled($area->postal_from);
        $hasCityRule = filled($area->city);

        if (! $hasPostalRule && ! $hasCityRule) {
            return false;
        }
        if ($hasCityRule && mb_strtolower(trim((string) $city)) !== mb_strtolower(trim($area->city))) {
            return false;
        }
        if (! $hasPostalRule) {
            return true;
        }

        $postal = $this->normalise($postalCode);
        $from = $this->normalise($area->postal_from);
        if ($postal === '') {
            return false;
        }
        if (blank($area->postal_to)) {
            return str_starts_with($postal, $from);
        }
        $to = $this->normalise($area->postal_to);
        if (ctype_digit($postal) && ctype_digit($from) && ctype_digit($to)) {
            return (int) $postal >= (int) $from && (int) $postal <= (int) $to;
        }

        return strcmp($postal, $from) >= 0 && strcmp($postal, $to) <= 0;
    }

    private function normalise(?string $postalCode): string
    {
        $value = strtoupper((string) preg_replace('/[\s-]+/', '', (string) $postalCode));

        return preg_match('/^\d{9}$/', $value) ? substr($value, 0, 5) : $value;
    }
}
