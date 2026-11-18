<?php

namespace App\Modules\Shipping\Services;

use App\Models\User;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingQuote;
use App\Modules\Shipping\Services\Rates\AgentRateChartProvider;
use App\Modules\Shipping\Services\Rates\RateProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function __construct(private ShippingAuditService $audit) {}

    /**
     * Stores a quotation with its surcharge lines and converts the total to the store currency.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(Shipment $shipment, array $data, ?User $user = null): ShippingQuote
    {
        return DB::transaction(function () use ($shipment, $data, $user): ShippingQuote {
            $currency = strtoupper((string) ($data['currency'] ?? $shipment->currency));
            $precision = ShippingMoney::precision($currency);
            $charges = collect($data['charges'] ?? [])->filter(fn (array $charge): bool => filled($charge['reason'] ?? null));
            $total = ShippingMoney::sum(array_merge([$data['base_rate'] ?? 0], $charges->pluck('amount')->all()), $precision);
            $rate = $currency === $shipment->currency ? '1' : (string) ($data['exchange_rate'] ?? '1');

            $quote = ShippingQuote::query()->create([
                'workspace_id' => $shipment->workspace_id,
                'shipping_shipment_id' => $shipment->id,
                'shipping_carrier_account_id' => $data['shipping_carrier_account_id'] ?? null,
                'shipping_agent_id' => $data['shipping_agent_id'] ?? null,
                'carrier' => $data['carrier'] ?? null,
                'service' => $data['service'] ?? null,
                'service_code' => $data['service_code'] ?? null,
                'source' => $data['source'],
                'billable_weight_kg' => $data['billable_weight_kg'] ?? null,
                'base_rate' => ShippingMoney::round($data['base_rate'] ?? 0, $precision),
                'total' => $total,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'total_store' => ShippingMoney::convert($total, $rate, ShippingMoney::precision($shipment->currency)),
                'transit_days' => $data['transit_days'] ?? null,
                'remote_status' => $data['remote_status'] ?? 'unknown',
                'status' => $data['status'] ?? 'unverified',
                'quoted_at' => $data['quoted_at'] ?? now(),
                'valid_until' => $data['valid_until'] ?? null,
                'created_by' => $user?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($charges as $charge) {
                $quote->charges()->create([
                    'workspace_id' => $shipment->workspace_id,
                    'reason' => $charge['reason'],
                    'label' => $charge['label'] ?? null,
                    'amount' => ShippingMoney::round($charge['amount'] ?? 0, $precision),
                ]);
            }

            $this->refreshEstimate($shipment);
            $this->audit->record($shipment->workspace_id, 'quote.added', 'Quotation added: '.$quote->providerLabel().' '.$quote->total.' '.$quote->currency, $shipment, null, ['quote_id' => $quote->id, 'total' => $quote->total, 'currency' => $quote->currency, 'source' => $quote->source], $user?->id);

            return $quote;
        });
    }

    /**
     * Re-rates the shipment from every active agent rate chart, replacing earlier chart quotes that were never approved.
     *
     * @return Collection<int, ShippingQuote>
     */
    public function requestRates(Shipment $shipment, ?User $user = null): Collection
    {
        $shipment->load('cartons');
        if ($shipment->cartons->isEmpty()) {
            throw ValidationException::withMessages(['cartons' => __('Add at least one carton before requesting rates.')]);
        }
        if (blank($shipment->country_code)) {
            throw ValidationException::withMessages(['country_code' => __('The destination country is required before requesting rates.')]);
        }

        return DB::transaction(function () use ($shipment, $user): Collection {
            $stale = $shipment->quotes()->whereIn('source', ['agent_chart', 'api'])->where('status', '!=', 'approved')->get();
            foreach ($stale as $quote) {
                $quote->charges()->delete();
                $quote->delete();
            }

            $created = collect();
            foreach ($this->providers() as $provider) {
                foreach ($provider->quotes($shipment) as $data) {
                    $created->push($this->store($shipment, $data, $user));
                }
            }

            if ($shipment->status === 'pending' && $shipment->quotes()->exists()) {
                $shipment->update(['status' => 'quoted']);
            }

            return $created;
        });
    }

    public function setStatus(ShippingQuote $quote, string $status, ?User $user = null): ShippingQuote
    {
        if ($quote->status === 'approved') {
            throw ValidationException::withMessages(['status' => __('An approved quotation cannot be changed.')]);
        }

        $old = $quote->status;
        $quote->update(['status' => $status]);
        $this->refreshEstimate($quote->shipment);
        $this->audit->record($quote->workspace_id, 'quote.'.$status, 'Quotation marked '.$status.': '.$quote->providerLabel(), $quote->shipment, ['status' => $old], ['status' => $status], $user?->id);

        return $quote;
    }

    /**
     * The estimate is the cheapest quotation that is still usable.
     */
    public function refreshEstimate(Shipment $shipment): void
    {
        $lowest = $shipment->quotes()->where('status', '!=', 'rejected')->get()
            ->reject(fn (ShippingQuote $quote): bool => $quote->isExpired())
            ->sortBy(fn (ShippingQuote $quote): float => (float) $quote->total_store)
            ->first();

        $shipment->update([
            'estimated_cost' => $lowest?->total_store,
            'status' => $shipment->status === 'pending' && $lowest ? 'quoted' : $shipment->status,
        ]);
    }

    /**
     * @return list<RateProvider>
     */
    private function providers(): array
    {
        $classes = (array) config('shipping_carriers.rate_providers', [AgentRateChartProvider::class]);

        return array_map(fn (string $class): RateProvider => app($class), $classes);
    }
}
