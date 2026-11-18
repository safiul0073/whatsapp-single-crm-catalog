<?php

namespace App\Modules\Shipping\Services;

/**
 * Rates cartons the way a courier does: every package is weighed on its own, the
 * larger of actual and dimensional weight is rounded up to the provider's step,
 * and the package totals are added together.
 */
class ShipmentWeightCalculator
{
    /**
     * @param  iterable<int, array{quantity?: int|string, actual_weight_kg: int|float|string, length_cm: int|float|string, width_cm: int|float|string, height_cm: int|float|string}>  $cartons
     * @param  array<string, int|float>|null  $limits
     * @return array{cartons: list<array<string, mixed>>, packages: int, actual_weight_kg: float, dimensional_weight_kg: float, billable_weight_kg: float, divisor: int, rounding_step: float, flags: list<string>}
     */
    public function calculate(iterable $cartons, ?int $divisor = null, string|float|null $roundingStep = null, ?array $limits = null): array
    {
        $divisor = max(1, $divisor ?: (int) config('shipping_carriers.default_dim_divisor', 5000));
        $step = (float) ($roundingStep ?: config('shipping_carriers.default_rounding_step', '0.5'));
        $step = $step > 0 ? $step : 0.5;
        $limits = $limits ?? (array) config('shipping_carriers.limits', []);

        $rows = [];
        $packages = 0;
        $actualTotal = 0.0;
        $dimensionalTotal = 0.0;
        $billableTotal = 0.0;
        $flags = [];

        foreach ($cartons as $carton) {
            $quantity = max(1, (int) ($carton['quantity'] ?? 1));
            $actual = (float) $carton['actual_weight_kg'];
            $sides = [(float) $carton['length_cm'], (float) $carton['width_cm'], (float) $carton['height_cm']];
            rsort($sides);
            [$longest, $second, $third] = $sides;

            $dimensional = round(($longest * $second * $third) / $divisor, 3);
            $billable = $this->roundUp(max($actual, $dimensional), $step);
            $cartonFlags = $this->flagsFor($actual, $dimensional, $longest, $second, $third, $limits);

            $rows[] = [
                'quantity' => $quantity,
                'actual_weight_kg' => $actual,
                'dimensional_weight_kg' => $dimensional,
                'billable_weight_kg' => $billable,
                'line_billable_weight_kg' => round($billable * $quantity, 3),
                'flags' => $cartonFlags,
            ];

            $packages += $quantity;
            $actualTotal += $actual * $quantity;
            $dimensionalTotal += $dimensional * $quantity;
            $billableTotal += $billable * $quantity;
            $flags = array_merge($flags, $cartonFlags);
        }

        if ($packages > 1) {
            $flags[] = 'multiple_packages';
        }

        return [
            'cartons' => $rows,
            'packages' => $packages,
            'actual_weight_kg' => round($actualTotal, 3),
            'dimensional_weight_kg' => round($dimensionalTotal, 3),
            'billable_weight_kg' => round($billableTotal, 3),
            'divisor' => $divisor,
            'rounding_step' => $step,
            'flags' => array_values(array_unique($flags)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function flagLabels(): array
    {
        return [
            'dimensional_weight' => 'Dimensional weight applies',
            'additional_handling' => 'Additional handling likely',
            'overweight' => 'Overweight package',
            'oversize' => 'Oversized carton',
            'multiple_packages' => 'Multiple-package shipment',
        ];
    }

    private function roundUp(float $weight, float $step): float
    {
        return round(ceil(($weight / $step) - 1e-9) * $step, 3);
    }

    /**
     * @param  array<string, int|float>  $limits
     * @return list<string>
     */
    private function flagsFor(float $actual, float $dimensional, float $longest, float $second, float $third, array $limits): array
    {
        $flags = [];
        $exceeds = fn (string $key, float $value): bool => isset($limits[$key]) && $limits[$key] > 0 && $value > (float) $limits[$key];

        if ($dimensional > $actual) {
            $flags[] = 'dimensional_weight';
        }
        if ($exceeds('overweight_kg', $actual)) {
            $flags[] = 'overweight';
        }
        if ($exceeds('oversize_longest_side_cm', $longest) || $exceeds('oversize_length_plus_girth_cm', $longest + (2 * ($second + $third)))) {
            $flags[] = 'oversize';
        }
        if ($exceeds('additional_handling_weight_kg', $actual) || $exceeds('additional_handling_longest_side_cm', $longest) || $exceeds('additional_handling_second_side_cm', $second)) {
            $flags[] = 'additional_handling';
        }

        return $flags;
    }
}
