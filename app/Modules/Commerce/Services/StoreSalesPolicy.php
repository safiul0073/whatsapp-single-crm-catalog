<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\StoreOrderSetting;

/**
 * What a store sells (retail, wholesale or both) and how its prices are written, for customer-facing assistants.
 */
class StoreSalesPolicy
{
    public function __construct(
        public readonly int $workspaceId,
        public readonly bool $retail,
        public readonly bool $wholesale,
        public readonly string $currency,
        public readonly int $precision,
    ) {}

    public static function for(int $workspaceId): self
    {
        $settings = StoreOrderSetting::forWorkspace($workspaceId);

        return new self(
            $workspaceId,
            StoreFeatures::retailEnabled($workspaceId),
            StoreFeatures::wholesaleEnabled($workspaceId),
            StoreOrderSetting::catalogCurrency($workspaceId),
            $settings->precision(),
        );
    }

    /**
     * @return 'retail'|'wholesale'|'both'
     */
    public function mode(): string
    {
        return match (true) {
            $this->retail && $this->wholesale => 'both',
            $this->wholesale => 'wholesale',
            default => 'retail',
        };
    }

    public function money(string|int|float|null $amount): ?string
    {
        if ($amount === null || (float) $amount <= 0) {
            return null;
        }

        return $this->currency.' '.OrderMoney::decimal(OrderMoney::minor($amount, $this->precision), $this->precision);
    }

    public function describe(): string
    {
        return match ($this->mode()) {
            'both' => 'This store sells both RETAIL (single pieces) and WHOLESALE (boxes/packs with a minimum order). Ask which one the customer wants when it matters, and quote the matching price.',
            'wholesale' => 'This store sells WHOLESALE ONLY. Never quote, mention or offer retail or single-piece prices. Always quote wholesale prices, minimum order quantities and box/pack details.',
            default => 'This store sells RETAIL ONLY. Never quote, mention or offer wholesale, bulk, box or pack prices.',
        };
    }
}
