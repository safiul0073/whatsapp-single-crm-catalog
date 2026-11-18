<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Contacts\Services\ContactFileReader;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Reads courier and agent invoices exported as Excel or CSV: one row per shipment,
 * with the tracking number, the amounts and any surcharge columns the courier uses.
 */
class InvoiceImportService
{
    private const HEADER_ALIASES = [
        'tracking_number' => ['tracking', 'tracking number', 'tracking no', 'tracking no.', 'tracking id', 'awb', 'awb number', 'awb no', 'waybill', 'waybill number', 'air waybill', 'airway bill', 'lead shipment number', 'shipment tracking number'],
        'reference' => ['reference', 'ref', 'shipment reference', 'customer reference', 'order', 'order number', 'order no', 'reference 1'],
        'ship_date' => ['date', 'ship date', 'shipment date', 'pickup date', 'invoice line date'],
        'billed_weight_kg' => ['billed weight', 'chargeable weight', 'weight', 'weight kg', 'billed weight kg', 'kg'],
        'base_amount' => ['base', 'base rate', 'base amount', 'freight', 'freight charge', 'transportation', 'transportation charge', 'shipping charge'],
        'total_amount' => ['total', 'total amount', 'total charge', 'total charges', 'amount', 'net amount', 'net charge', 'billed amount', 'invoice amount', 'line total'],
    ];

    private const CHARGE_ALIASES = [
        'fuel' => ['fuel', 'fuel surcharge', 'fsc'],
        'remote_area' => ['remote', 'remote area', 'remote area surcharge', 'extended area', 'extended area surcharge', 'delivery area surcharge', 'das', 'out of delivery area', 'oda'],
        'additional_handling' => ['additional handling', 'ahs', 'handling'],
        'oversize' => ['oversize', 'large package', 'large package surcharge'],
        'overweight' => ['overweight', 'over weight', 'overmax'],
        'address_correction' => ['address correction', 'address correction fee'],
        'customs' => ['customs', 'duty', 'duties', 'tax', 'taxes', 'duties and taxes', 'brokerage', 'clearance'],
        'other' => ['other', 'other charges', 'misc', 'miscellaneous', 'surcharge', 'surcharges'],
    ];

    public function __construct(private ContactFileReader $reader) {}

    /**
     * @return array{rows: list<array{tracking_number: ?string, reference: ?string, ship_date: ?string, billed_weight_kg: ?string, base_amount: string, total_amount: string, charges: list<array{reason: string, amount: string}>}>, skipped: int}
     */
    public function parse(string $absolutePath): array
    {
        $sheet = $this->reader->read($absolutePath);
        [$columns, $chargeColumns] = $this->mapHeaders($sheet['headers'] ?? []);

        if (! isset($columns['tracking_number']) && ! isset($columns['reference'])) {
            throw ValidationException::withMessages(['file' => __('The invoice needs a tracking number (or AWB / waybill) column, or a shipment reference column.')]);
        }
        if (! isset($columns['total_amount']) && ! isset($columns['base_amount'])) {
            throw ValidationException::withMessages(['file' => __('The invoice needs an amount column, for example "total" or "freight".')]);
        }

        $rows = [];
        $skipped = 0;
        foreach ($sheet['rows'] ?? [] as $raw) {
            $value = fn (?int $index): ?string => $index !== null && trim((string) ($raw[$index] ?? '')) !== '' ? trim((string) $raw[$index]) : null;
            $tracking = $value($columns['tracking_number'] ?? null);
            $reference = $value($columns['reference'] ?? null);
            $charges = [];
            foreach ($chargeColumns as $index => $reason) {
                $amount = $this->number($value($index));
                if ($amount !== null && (float) $amount > 0) {
                    $charges[] = ['reason' => $reason, 'amount' => $amount];
                }
            }
            $base = $this->number($value($columns['base_amount'] ?? null));
            $total = $this->number($value($columns['total_amount'] ?? null));
            $chargeTotal = ShippingMoney::sum(array_column($charges, 'amount'), 4);

            $unreadableTotal = $total === null && $value($columns['total_amount'] ?? null) !== null;
            if (($tracking === null && $reference === null) || ($base === null && $total === null) || $unreadableTotal) {
                $skipped++;

                continue;
            }

            $total ??= ShippingMoney::sum([$base, $chargeTotal], 4);
            if ($base === null) {
                $base = ShippingMoney::isGreater($chargeTotal, $total, 4) ? $total : ShippingMoney::difference($total, $chargeTotal, 4);
            }

            $rows[] = [
                'tracking_number' => $tracking !== null ? mb_substr(preg_replace('/\s+/', '', $tracking), 0, 80) : null,
                'reference' => $reference !== null ? mb_substr($reference, 0, 80) : null,
                'ship_date' => $this->date($value($columns['ship_date'] ?? null)),
                'billed_weight_kg' => $this->number($value($columns['billed_weight_kg'] ?? null)),
                'base_amount' => $base,
                'total_amount' => $total,
                'charges' => $charges,
            ];
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * @param  list<string>  $headers
     * @return array{0: array<string, int>, 1: array<int, string>}
     */
    private function mapHeaders(array $headers): array
    {
        $columns = [];
        $charges = [];
        foreach ($headers as $index => $header) {
            $name = strtolower(trim((string) preg_replace('/[\s_()#:]+/', ' ', str_replace(['(kg)', '(KG)'], ' kg', (string) $header))));
            $matched = false;
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (! isset($columns[$field]) && in_array($name, $aliases, true)) {
                    $columns[$field] = $index;
                    $matched = true;

                    break;
                }
            }
            if ($matched) {
                continue;
            }
            foreach (self::CHARGE_ALIASES as $reason => $aliases) {
                if (in_array($name, $aliases, true)) {
                    $charges[$index] = $reason;

                    break;
                }
            }
        }

        return [$columns, $charges];
    }

    /**
     * Credits (negative amounts) are not imported: their rows are counted as skipped for staff to review.
     */
    private function number(?string $value): ?string
    {
        if ($value === null || str_starts_with(trim($value), '-') || (str_starts_with(trim($value), '(') && str_ends_with(trim($value), ')'))) {
            return null;
        }
        $clean = preg_replace('/[^\d.]/', '', str_replace(',', '', $value));

        return $clean !== '' && is_numeric($clean) ? $clean : null;
    }

    private function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        return rescue(fn (): string => Carbon::parse($value)->toDateString(), null, false);
    }
}
