<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;

class GarmentPricingService
{
    /**
     * Compute full price breakdown for garment product order.
     */
    public function compute(Product $product, int $quantity = 1, array $colorSizeMatrix = []): array
    {
        $quantity = max(1, $quantity);

        // If matrix is passed, calculate total from matrix if greater
        $matrixTotal = 0;
        foreach ($colorSizeMatrix as $colorKey => $sizes) {
            if (is_array($sizes)) {
                foreach ($sizes as $size => $qty) {
                    $matrixTotal += max(0, (int) $qty);
                }
            }
        }

        if ($matrixTotal > 0) {
            $quantity = $matrixTotal;
        }

        $breakdown = $product->calculateCostBreakdown($quantity);
        $breakdown['matrix'] = $colorSizeMatrix;

        return $breakdown;
    }

    /**
     * Direct calculation with explicit parameters.
     */
    public function calculateCost(
        Product $product,
        int $quantity = 1,
        ?float $unitPrice = null,
        ?float $unitWeightKg = null,
        float $baseShippingRatePerKg = 50.00,
        float $minShippingKg = 1.0
    ): array {
        return $product->calculateCostBreakdown(
            quantity: $quantity,
            baseShippingRatePerKg: $baseShippingRatePerKg,
            minShippingKg: $minShippingKg,
            unitWeightKg: $unitWeightKg
        );
    }

    /**
     * Build formatted WhatsApp message text for inquiry or ordering.
     */
    public function buildWhatsAppText(Product $product, int $quantity = 1, array $colorSizeMatrix = [], ?string $currency = 'USD'): string
    {
        $breakdown = $this->compute($product, $quantity, $colorSizeMatrix);
        $currencySymbol = match (strtoupper($currency ?: 'USD')) {
            'BDT' => '৳',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            default => $currency.' ',
        };

        $lines = [];
        $lines[] = '👗 *Garment Order / Inquiry*';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━';
        $lines[] = "*Product:* {$product->name}";
        if (filled($product->fabric_gsm)) {
            $lines[] = "*Fabric:* {$product->fabric_gsm}".(filled($product->material) ? " ({$product->material})" : '');
        }

        // Color & Size Matrix Breakdown if provided
        if (! empty($breakdown['matrix'])) {
            $lines[] = '';
            $lines[] = '🎨 *Selected Colors & Sizes:*';
            foreach ($breakdown['matrix'] as $colorIdentifier => $sizes) {
                $colorName = $colorIdentifier;
                if (is_numeric($colorIdentifier)) {
                    $color = ProductColor::query()->find((int) $colorIdentifier);
                    if ($color) {
                        $colorName = $color->display_name;
                    }
                }
                $sizeParts = [];
                foreach ($sizes as $size => $qty) {
                    $qty = (int) $qty;
                    if ($qty > 0) {
                        $sizeParts[] = "{$size}: {$qty} pcs";
                    }
                }
                if (! empty($sizeParts)) {
                    $lines[] = "• *{$colorName}*: ".implode(', ', $sizeParts);
                }
            }
        }

        $lines[] = '';
        $lines[] = '📊 *Price & Shipping Breakdown:*';
        $lines[] = "• Total Quantity: *{$breakdown['quantity']} pcs*";
        $lines[] = "• Unit Price: *{$currencySymbol}".number_format($breakdown['unit_price'], 2).'/pc*';
        $lines[] = "• Garment Subtotal: *{$currencySymbol}".number_format($breakdown['garment_subtotal'], 2).'*';
        $lines[] = "• Est. Weight: *{$breakdown['total_weight_kg']} kg*";
        $lines[] = "• Est. Shipping: *{$currencySymbol}".number_format($breakdown['shipping_cost'], 2).'*';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━';
        $lines[] = "💰 *Total Landed Cost: {$currencySymbol}".number_format($breakdown['total_landed_cost'], 2).'*';
        $lines[] = "💡 *Effective Cost Per Piece: {$currencySymbol}".number_format($breakdown['effective_price_per_unit'], 2).'/pc*';

        if ($breakdown['total_savings'] > 0) {
            $lines[] = "🎉 *Wholesale Savings: {$currencySymbol}".number_format($breakdown['total_savings'], 2).' total*';
        }

        return implode("\n", $lines);
    }
}
