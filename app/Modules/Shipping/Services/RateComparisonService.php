<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingQuote;
use Illuminate\Support\Collection;

class RateComparisonService
{
    /**
     * Quotations for a shipment, cheapest first, with the lowest usable one marked.
     *
     * @return Collection<int, array{quote: ShippingQuote, is_lowest: bool, is_expired: bool, is_confirmed: bool, charges_by_reason: array<string, string>}>
     */
    public function forShipment(Shipment $shipment): Collection
    {
        $quotes = $shipment->quotes()->with(['charges', 'agent', 'carrierAccount', 'creator'])->get()
            ->sortBy(fn (ShippingQuote $quote): float => (float) $quote->total_store)
            ->values();

        $lowestId = $quotes
            ->filter(fn (ShippingQuote $quote): bool => $quote->status !== 'rejected' && ! $quote->isExpired())
            ->first()?->id;

        return $quotes->map(function (ShippingQuote $quote) use ($lowestId): array {
            $precision = ShippingMoney::precision($quote->currency);

            return [
                'quote' => $quote,
                'is_lowest' => $quote->id === $lowestId,
                'is_expired' => $quote->isExpired(),
                'is_confirmed' => $quote->isConfirmedPrice(),
                'charges_by_reason' => $quote->charges->groupBy('reason')
                    ->map(fn (Collection $charges): string => ShippingMoney::sum($charges->pluck('amount'), $precision))
                    ->all(),
            ];
        });
    }
}
