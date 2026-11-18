<?php

namespace App\Modules\Shipping\Services\Rates;

use App\Modules\Shipping\Models\AgentRateChart;
use App\Modules\Shipping\Models\AgentRateRow;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Services\RemoteAreaService;
use App\Modules\Shipping\Services\ShipmentWeightCalculator;
use App\Modules\Shipping\Services\ShippingMoney;

class AgentRateChartProvider implements RateProvider
{
    public function __construct(
        private ShipmentWeightCalculator $weights,
        private RemoteAreaService $remoteAreas,
    ) {}

    public function quotes(Shipment $shipment): array
    {
        $shipment->loadMissing('cartons');
        if ($shipment->cartons->isEmpty() || blank($shipment->country_code)) {
            return [];
        }

        $quotes = [];
        $agents = ShippingAgent::query()->where('workspace_id', $shipment->workspace_id)->active()->get();

        foreach ($agents as $agent) {
            $quote = $this->quoteForAgent($shipment, $agent);
            if ($quote !== null) {
                $quotes[] = $quote;
            }
        }

        return $quotes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function quoteForAgent(Shipment $shipment, ShippingAgent $agent): ?array
    {
        $weight = $this->weights->calculate($shipment->cartons->toArray(), $agent->dim_divisor, $agent->weight_rounding_step);
        $billable = $weight['billable_weight_kg'];

        $chart = AgentRateChart::query()
            ->where('workspace_id', $shipment->workspace_id)
            ->where('shipping_agent_id', $agent->id)
            ->usable()
            ->whereHas('rows', fn ($rows) => $this->matchingRows($rows, $shipment->country_code, $billable))
            ->latest('effective_from')
            ->latest('id')
            ->first();

        if (! $chart) {
            return null;
        }

        /** @var AgentRateRow $row */
        $row = $this->matchingRows($chart->rows(), $shipment->country_code, $billable)
            ->orderByRaw('country_code is null')
            ->orderByDesc('min_weight_kg')
            ->first();

        $precision = ShippingMoney::precision($chart->currency);
        $base = ShippingMoney::sum([
            $row->price,
            $row->price_per_kg !== null ? ShippingMoney::convert($row->price_per_kg, $billable, $precision) : 0,
        ], $precision);

        $charges = [];
        if ((float) $agent->fuel_surcharge_percent > 0) {
            $charges[] = ['reason' => 'fuel', 'label' => 'Fuel surcharge '.rtrim(rtrim((string) $agent->fuel_surcharge_percent, '0'), '.').'%', 'amount' => ShippingMoney::percentOf($base, $agent->fuel_surcharge_percent, $precision)];
        }

        $remote = $this->remoteAreas->statusFor($shipment->workspace_id, $shipment->country_code, $shipment->postal_code, $shipment->city, $agent->carrier, $agent->id);
        if (in_array($remote['status'], ['remote', 'extended_remote'], true) && (float) $remote['fee'] > 0) {
            $charges[] = ['reason' => 'remote_area', 'label' => $remote['label'].' surcharge', 'amount' => ShippingMoney::round($remote['fee'], $precision)];
        }
        if ((float) $agent->other_fee > 0) {
            $charges[] = ['reason' => 'other', 'label' => $agent->other_fee_label ?: 'Other fees', 'amount' => ShippingMoney::round($agent->other_fee, $precision)];
        }
        foreach (['overweight' => 'overweight', 'oversize' => 'oversize', 'additional_handling' => 'additional_handling'] as $flag => $reason) {
            if (in_array($flag, $weight['flags'], true)) {
                $charges[] = ['reason' => $reason, 'label' => ShipmentWeightCalculator::flagLabels()[$flag].' — fee to be confirmed', 'amount' => '0'];
            }
        }

        return [
            'shipping_agent_id' => $agent->id,
            'carrier' => $agent->carrier,
            'service' => $chart->name,
            'source' => 'agent_chart',
            'billable_weight_kg' => $billable,
            'base_rate' => $base,
            'currency' => $chart->currency,
            'exchange_rate' => $chart->currency === $shipment->currency ? '1' : (string) $agent->exchange_rate,
            'transit_days' => $row->transit_days,
            'remote_status' => $remote['status'],
            'status' => 'verified',
            'valid_until' => $chart->expires_on?->endOfDay(),
            'notes' => 'Rate chart: '.$chart->name,
            'charges' => $charges,
        ];
    }

    private function matchingRows($rows, string $countryCode, float $billable)
    {
        return $rows
            ->where(fn ($country) => $country->where('country_code', strtoupper($countryCode))->orWhereNull('country_code'))
            ->where('min_weight_kg', '<=', $billable)
            ->where(fn ($max) => $max->whereNull('max_weight_kg')->orWhere('max_weight_kg', '>=', $billable));
    }
}
