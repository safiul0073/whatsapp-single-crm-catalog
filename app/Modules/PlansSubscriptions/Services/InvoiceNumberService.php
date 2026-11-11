<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\PaymentGateways\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sequential, per-year invoice numbers (INV-2026-000001) for completed plan payments.
 */
class InvoiceNumberService
{
    public function assign(Payment $payment): string
    {
        if ($existing = data_get($payment->metadata, 'invoice_number')) {
            return (string) $existing;
        }

        return DB::transaction(function () use ($payment): string {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($existing = data_get($payment->metadata, 'invoice_number')) {
                return (string) $existing;
            }

            $year = ($payment->paid_at ?? now())->format('Y');
            $lock = Cache::lock("plan-invoice-number-{$year}", 10);
            $lock->block(5);

            try {
                $prefix = "INV-{$year}-";
                $last = Payment::query()
                    ->where('metadata->invoice_number', 'like', $prefix.'%')
                    ->pluck('metadata')
                    ->map(fn (mixed $metadata): int => (int) str((string) data_get($metadata, 'invoice_number'))->after($prefix)->toString())
                    ->max() ?? 0;

                $number = $prefix.str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);

                $payment->forceFill(['metadata' => array_merge($payment->metadata ?? [], ['invoice_number' => $number])])->save();

                return $number;
            } finally {
                $lock->release();
            }
        });
    }
}
