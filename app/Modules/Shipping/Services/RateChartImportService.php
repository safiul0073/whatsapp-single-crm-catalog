<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Contacts\Services\ContactFileReader;
use App\Modules\Shipping\Models\AgentRateChart;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an Excel or CSV rate sheet into rate rows. PDF charts are only stored:
 * someone has to key the rows in and review them before the chart can be activated.
 */
class RateChartImportService
{
    private const HEADER_ALIASES = [
        'country_code' => ['country', 'country code', 'country_code', 'destination', 'iso', 'iso2'],
        'zone' => ['zone', 'region'],
        'min_weight_kg' => ['min weight', 'min_weight', 'min_weight_kg', 'min kg', 'weight from', 'from kg', 'from'],
        'max_weight_kg' => ['max weight', 'max_weight', 'max_weight_kg', 'max kg', 'weight to', 'to kg', 'to', 'weight', 'weight kg', 'weight_kg', 'kg'],
        'price' => ['price', 'rate', 'amount', 'cost', 'base', 'base rate'],
        'price_per_kg' => ['price per kg', 'price_per_kg', 'per kg', 'rate per kg', 'per_kg'],
        'transit_days' => ['transit', 'transit days', 'transit_days', 'days', 'delivery time', 'eta'],
    ];

    public function __construct(private ContactFileReader $reader) {}

    /**
     * @return array{rows: list<array<string, mixed>>, skipped: int}
     */
    public function parse(string $absolutePath): array
    {
        $sheet = $this->reader->read($absolutePath);
        $columns = $this->mapHeaders($sheet['headers'] ?? []);

        if (! isset($columns['price']) && ! isset($columns['price_per_kg'])) {
            throw ValidationException::withMessages(['file' => __('The sheet needs a "price" or "price per kg" column. Expected columns: country, zone, min weight, max weight, price, price per kg, transit days.')]);
        }
        if (! isset($columns['max_weight_kg']) && ! isset($columns['min_weight_kg'])) {
            throw ValidationException::withMessages(['file' => __('The sheet needs a weight column (for example "max weight" or "weight").')]);
        }

        $rows = [];
        $skipped = 0;
        foreach ($sheet['rows'] ?? [] as $raw) {
            $value = fn (string $field): ?string => isset($columns[$field]) && trim((string) ($raw[$columns[$field]] ?? '')) !== '' ? trim((string) $raw[$columns[$field]]) : null;
            $price = $this->number($value('price'));
            $perKg = $this->number($value('price_per_kg'));
            $max = $this->number($value('max_weight_kg'));
            $min = $this->number($value('min_weight_kg'));
            $country = $value('country_code');

            if (($price === null && $perKg === null) || ($max === null && $min === null) || ($country !== null && ! preg_match('/^[A-Za-z]{2}$/', $country))) {
                $skipped++;

                continue;
            }

            $rows[] = [
                'country_code' => $country !== null ? strtoupper($country) : null,
                'zone' => $value('zone'),
                'min_weight_kg' => $min,
                'max_weight_kg' => $max,
                'price' => $price ?? '0',
                'price_per_kg' => $perKg,
                'transit_days' => $value('transit_days'),
            ];
        }

        return ['rows' => $this->fillMissingMinimums($rows), 'skipped' => $skipped];
    }

    /**
     * Replaces the rows of a chart with the parsed sheet. The chart stays pending review.
     *
     * @return array{imported: int, skipped: int}
     */
    public function import(AgentRateChart $chart, string $absolutePath): array
    {
        $parsed = $this->parse($absolutePath);
        if ($parsed['rows'] === []) {
            throw ValidationException::withMessages(['file' => __('No usable rate rows were found in this file.')]);
        }

        DB::transaction(function () use ($chart, $parsed): void {
            $chart->rows()->delete();
            foreach (array_chunk($parsed['rows'], 500) as $chunk) {
                $chart->rows()->createMany(array_map(fn (array $row): array => $row + ['workspace_id' => $chart->workspace_id], $chunk));
            }
        });

        return ['imported' => count($parsed['rows']), 'skipped' => $parsed['skipped']];
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function mapHeaders(array $headers): array
    {
        $columns = [];
        foreach ($headers as $index => $header) {
            $name = strtolower(trim((string) preg_replace('/[\s_()]+/', ' ', str_replace(['(kg)', '(KG)'], '', (string) $header))));
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (! isset($columns[$field]) && in_array($name, array_map(fn (string $alias): string => str_replace('_', ' ', $alias), $aliases), true)) {
                    $columns[$field] = $index;

                    break;
                }
            }
        }

        return $columns;
    }

    private function number(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = preg_replace('/[^\d.]/', '', str_replace(',', '', $value));

        return $clean !== '' && is_numeric($clean) ? $clean : null;
    }

    /**
     * Weight-break sheets list only the upper weight; each row then starts just above the previous break.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function fillMissingMinimums(array $rows): array
    {
        $previous = [];
        usort($rows, fn (array $first, array $second): int => [$first['country_code'], $first['zone'], (float) ($first['max_weight_kg'] ?? $first['min_weight_kg'])] <=> [$second['country_code'], $second['zone'], (float) ($second['max_weight_kg'] ?? $second['min_weight_kg'])]);

        foreach ($rows as &$row) {
            $key = ($row['country_code'] ?? '*').'|'.($row['zone'] ?? '*');
            if ($row['min_weight_kg'] === null) {
                $row['min_weight_kg'] = isset($previous[$key]) ? (string) round($previous[$key] + 0.001, 3) : '0';
            }
            if ($row['max_weight_kg'] !== null) {
                $previous[$key] = (float) $row['max_weight_kg'];
            }
        }

        return $rows;
    }
}
