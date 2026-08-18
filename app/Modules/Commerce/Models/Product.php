<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'commerce_products';

    protected $fillable = [
        'workspace_id',
        'category_id',
        'brand_id',
        'audience_id',
        'primary_media_id',
        'name',
        'slug',
        'brand',
        'description',
        'care_information',
        'condition',
        'audience',
        'fabric_gsm',
        'material',
        'default_unit_weight_kg',
        'single_piece_price',
        'wholesale_price',
        'country_of_origin',
        'status',
        'wizard_step',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'wizard_step' => 'integer',
            'published_at' => 'datetime',
            'default_unit_weight_kg' => 'decimal:3',
            'single_piece_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brandRecord(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function audienceRecord(): BelongsTo
    {
        return $this->belongsTo(Audience::class, 'audience_id');
    }

    public function primaryMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'primary_media_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)->orderBy('position');
    }

    public function tierPrices(): HasMany
    {
        return $this->hasMany(ProductTierPrice::class)->orderBy('min_quantity');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function gallery(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->with('media')->orderBy('position');
    }

    /**
     * Resolve effective unit price for given quantity or mode (single vs wholesale).
     */
    public function resolveUnitPrice(int $quantity = 1, ?string $mode = null): float
    {
        $quantity = max(1, $quantity);

        if ($mode === 'single' || ($mode === null && $quantity === 1)) {
            if ($this->single_piece_price !== null && (float) $this->single_piece_price > 0) {
                return (float) $this->single_piece_price;
            }
        }

        if ($mode === 'wholesale' || ($mode === null && $quantity > 1)) {
            if ($this->wholesale_price !== null && (float) $this->wholesale_price > 0) {
                return (float) $this->wholesale_price;
            }
        }

        if ($this->single_piece_price !== null && (float) $this->single_piece_price > 0) {
            return (float) $this->single_piece_price;
        }

        $firstVariantPrice = $this->variants->first()?->price;
        if ($firstVariantPrice !== null && (float) $firstVariantPrice > 0) {
            return (float) $firstVariantPrice;
        }

        return 0.0;
    }

    /**
     * Calculate comprehensive garment cost, weight, shipping, and landed per-unit price.
     */
    public function calculateCostBreakdown(
        int $quantity,
        float $baseShippingRatePerKg = 50.00,
        float $minShippingKg = 1.0,
        ?float $unitWeightKg = null
    ): array {
        $quantity = max(1, $quantity);
        $singlePrice = $this->resolveUnitPrice(1, 'single');
        $unitPrice = $quantity === 1 ? $singlePrice : $this->resolveUnitPrice($quantity, 'wholesale');
        $garmentSubtotal = round($unitPrice * $quantity, 2);

        $unitWeightKg = $unitWeightKg !== null ? (float) $unitWeightKg : (float) ($this->default_unit_weight_kg ?: 0.030);
        $totalWeightKg = round(max(0.001, $unitWeightKg * $quantity), 3);

        // International parcel shipping rules:
        // - Minimum chargeable parcel is 1.0 kg = $50.00 base shipping
        // - Additional weight: $50.00 for every additional 1.0 kg (or prorated bracket)
        $chargeableWeightKg = max($minShippingKg, ceil($totalWeightKg));
        $shippingCost = round($chargeableWeightKg * $baseShippingRatePerKg, 2);

        $totalLandedCost = round($garmentSubtotal + $shippingCost, 2);
        $effectivePricePerUnit = round($totalLandedCost / $quantity, 2);

        // Calculate retail 1-pc landed cost for comparison
        $singleShippingCost = round($minShippingKg * $baseShippingRatePerKg, 2);
        $singleLandedCost = round($singlePrice + $singleShippingCost, 2);
        $savingsPerUnit = max(0.0, round($singleLandedCost - $effectivePricePerUnit, 2));
        $totalSavings = round($savingsPerUnit * $quantity, 2);

        return [
            'quantity' => $quantity,
            'total_quantity' => $quantity,
            'unit_price' => $unitPrice,
            'single_price' => $singlePrice,
            'garment_subtotal' => $garmentSubtotal,
            'unit_weight_kg' => $unitWeightKg,
            'total_weight_kg' => $totalWeightKg,
            'chargeable_weight_kg' => $chargeableWeightKg,
            'shipping_cost' => $shippingCost,
            'total_landed_cost' => $totalLandedCost,
            'effective_price_per_unit' => $effectivePricePerUnit,
            'single_landed_cost' => $singleLandedCost,
            'savings_per_unit' => $savingsPerUnit,
            'total_savings' => $totalSavings,
            'is_wholesale' => $quantity >= 10,
        ];
    }
}
