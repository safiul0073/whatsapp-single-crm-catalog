<?php

namespace App\Modules\Shipping\Services;

use App\Models\User;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingInvoice;
use App\Modules\Shipping\Models\ShippingPayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records payments already made to agents and couriers, with their receipts.
 * Nothing here sends money: it is the store's own payment ledger.
 */
class PaymentService
{
    public const DISK = 'local';

    public function __construct(
        private InvoiceService $invoices,
        private ShippingAuditService $audit,
    ) {}

    /**
     * @param  array{provider: string, shipping_invoice_id?: ?int, amount: string|float, currency: string, paid_on: string, method: string, reference?: ?string, notes?: ?string}  $data
     */
    public function record(int $workspaceId, array $data, ?UploadedFile $receipt, User $user): ShippingPayment
    {
        $provider = $this->invoices->provider($workspaceId, $data['provider']);
        if (! $provider['agent'] && ! $provider['account']) {
            throw ValidationException::withMessages(['provider' => __('Choose the agent or courier account that was paid.')]);
        }

        $currency = strtoupper($data['currency']);
        $invoice = null;
        if (filled($data['shipping_invoice_id'] ?? null)) {
            $invoice = ShippingInvoice::query()->where('workspace_id', $workspaceId)->findOrFail((int) $data['shipping_invoice_id']);
            if ($invoice->shipping_agent_id !== $provider['agent']?->id || $invoice->shipping_carrier_account_id !== $provider['account']?->id) {
                throw ValidationException::withMessages(['shipping_invoice_id' => __('This invoice belongs to another provider.')]);
            }
            if ($currency !== $invoice->currency) {
                throw ValidationException::withMessages(['currency' => __('Invoice :number is in :currency.', ['number' => $invoice->invoice_number, 'currency' => $invoice->currency])]);
            }
            $outstanding = $invoice->outstanding();
            $remaining = str_starts_with($outstanding, '-') ? ShippingMoney::round(0, ShippingMoney::precision($currency)) : $outstanding;
            if (ShippingMoney::isGreater($data['amount'], $remaining, ShippingMoney::precision($currency))) {
                throw ValidationException::withMessages(['amount' => __('Only :amount :currency is still owed on invoice :number.', ['amount' => $remaining, 'currency' => $currency, 'number' => $invoice->invoice_number])]);
            }
        } elseif ($provider['agent'] && $currency !== strtoupper($provider['agent']->currency)) {
            throw ValidationException::withMessages(['currency' => __(':agent is paid in :currency.', ['agent' => $provider['agent']->name, 'currency' => $provider['agent']->currency])]);
        }

        $payment = DB::transaction(function () use ($workspaceId, $data, $receipt, $user, $provider, $currency, $invoice): ShippingPayment {
            $extension = $receipt ? strtolower($receipt->getClientOriginalExtension() ?: 'pdf') : null;
            $payment = ShippingPayment::query()->create([
                'workspace_id' => $workspaceId,
                'shipping_agent_id' => $provider['agent']?->id,
                'shipping_carrier_account_id' => $provider['account']?->id,
                'shipping_invoice_id' => $invoice?->id,
                'amount' => ShippingMoney::round($data['amount'], ShippingMoney::precision($currency)),
                'currency' => $currency,
                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'receipt_path' => $receipt?->storeAs('shipping-receipts/'.$workspaceId, Str::random(16).'.'.$extension, self::DISK),
                'receipt_original_name' => $receipt ? mb_substr($receipt->getClientOriginalName(), 0, 255) : null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
            ]);

            if ($invoice) {
                $this->refreshInvoice($invoice);
            }
            if ($provider['agent']) {
                $this->refreshAgentStatus($provider['agent']);
            }

            return $payment;
        });

        $this->audit->record($workspaceId, 'payment.recorded', 'Payment of '.$payment->amount.' '.$currency.' recorded for '.$payment->providerLabel().($invoice ? ' (invoice '.$invoice->invoice_number.')' : ''), null, null, ['payment_id' => $payment->id, 'amount' => $payment->amount, 'currency' => $currency], $user->id);

        return $payment;
    }

    public function delete(ShippingPayment $payment, User $user): void
    {
        DB::transaction(function () use ($payment): void {
            $payment->delete();
            if ($payment->invoice) {
                $this->refreshInvoice($payment->invoice);
            }
            if ($payment->agent) {
                $this->refreshAgentStatus($payment->agent);
            }
        });

        if ($payment->receipt_path) {
            Storage::disk(self::DISK)->delete($payment->receipt_path);
        }
        $this->audit->record($payment->workspace_id, 'payment.deleted', 'Payment of '.$payment->amount.' '.$payment->currency.' removed for '.$payment->providerLabel(), null, ['amount' => $payment->amount, 'currency' => $payment->currency], null, $user->id);
    }

    public function refreshInvoice(ShippingInvoice $invoice): void
    {
        $precision = ShippingMoney::precision($invoice->currency);
        $paid = ShippingMoney::sum($invoice->payments()->pluck('amount'), $precision);
        $due = $invoice->amountDue();

        $invoice->update([
            'paid_amount' => $paid,
            'payment_status' => match (true) {
                ! ShippingMoney::isGreater($paid, 0, $precision) => 'unpaid',
                ! ShippingMoney::isGreater($due, $paid, $precision) => 'paid',
                default => 'partial',
            },
        ]);
        $this->invoices->syncShipmentPayments($invoice);
        if ($invoice->agent) {
            $this->refreshAgentStatus($invoice->agent);
        }
    }

    /**
     * clear = nothing owed, due = something owed, overdue = an invoice is past its due date.
     */
    public function refreshAgentStatus(ShippingAgent $agent): void
    {
        $balance = $this->balance($agent);
        $owes = ! str_starts_with($balance, '-') && ShippingMoney::isGreater($balance, 0, ShippingMoney::precision($agent->currency));
        $overdue = $agent->invoices()->where('payment_status', '!=', 'paid')->whereNotNull('due_date')->whereDate('due_date', '<', today())->exists();

        $agent->update(['payment_status' => match (true) {
            ! $owes => 'clear',
            $overdue => 'overdue',
            default => 'due',
        }]);
    }

    /**
     * What the store still owes the agent: all invoices minus all payments, in the agent's currency.
     */
    public function balance(ShippingAgent $agent): string
    {
        $precision = ShippingMoney::precision($agent->currency);
        $billed = ShippingMoney::sum($agent->invoices()->get()->map(fn (ShippingInvoice $invoice): string => $invoice->amountDue()), $precision);
        $paid = ShippingMoney::sum($agent->payments()->pluck('amount'), $precision);

        return ShippingMoney::difference($billed, $paid, $precision);
    }

    /**
     * A running account of invoices (owed) and payments (paid) for one agent.
     *
     * @return array{opening: string, closing: string, billed: string, paid: string, entries: Collection<int, array{date: Carbon, type: string, description: string, debit: ?string, credit: ?string, balance: string, model: ShippingInvoice|ShippingPayment}>}
     */
    public function statement(ShippingAgent $agent, Carbon $from, Carbon $to): array
    {
        $precision = ShippingMoney::precision($agent->currency);
        $invoiceDate = fn (ShippingInvoice $invoice): Carbon => $invoice->invoice_date ?? $invoice->created_at;

        $invoices = $agent->invoices()->get();
        $payments = $agent->payments()->with('invoice')->get();

        $openingBilled = ShippingMoney::sum($invoices->filter(fn (ShippingInvoice $invoice): bool => $invoiceDate($invoice)->lt($from))->map->amountDue(), $precision);
        $openingPaid = ShippingMoney::sum($payments->filter(fn (ShippingPayment $payment): bool => $payment->paid_on->lt($from))->pluck('amount'), $precision);
        $balance = ShippingMoney::difference($openingBilled, $openingPaid, $precision);
        $opening = $balance;

        $rows = $invoices
            ->filter(fn (ShippingInvoice $invoice): bool => $invoiceDate($invoice)->betweenIncluded($from, $to))
            ->map(fn (ShippingInvoice $invoice): array => ['date' => $invoiceDate($invoice), 'type' => 'invoice', 'description' => __('Invoice :number', ['number' => $invoice->invoice_number]), 'debit' => $invoice->amountDue(), 'credit' => null, 'model' => $invoice])
            ->concat($payments
                ->filter(fn (ShippingPayment $payment): bool => $payment->paid_on->betweenIncluded($from, $to))
                ->map(fn (ShippingPayment $payment): array => ['date' => $payment->paid_on, 'type' => 'payment', 'description' => __('Payment — :method', ['method' => __($payment->methodLabel())]).($payment->reference ? ' ('.$payment->reference.')' : '').($payment->invoice ? ' · '.$payment->invoice->invoice_number : ''), 'debit' => null, 'credit' => ShippingMoney::round($payment->amount, $precision), 'model' => $payment]))
            ->sortBy(fn (array $row): string => $row['date']->format('Y-m-d').($row['type'] === 'invoice' ? '0' : '1'))
            ->values();

        $billed = [];
        $paid = [];
        $entries = $rows->map(function (array $row) use (&$balance, &$billed, &$paid, $precision): array {
            if ($row['debit'] !== null) {
                $balance = $this->add($balance, $row['debit'], $precision);
                $billed[] = $row['debit'];
            }
            if ($row['credit'] !== null) {
                $balance = $this->add($balance, '-'.$row['credit'], $precision);
                $paid[] = $row['credit'];
            }

            return $row + ['balance' => $balance];
        });

        return [
            'opening' => $opening,
            'closing' => $balance,
            'billed' => ShippingMoney::sum($billed, $precision),
            'paid' => ShippingMoney::sum($paid, $precision),
            'entries' => $entries,
        ];
    }

    /**
     * Adds two signed decimal strings.
     */
    private function add(string $left, string $right, int $precision): string
    {
        $minor = fn (string $value): int => (str_starts_with($value, '-') ? -1 : 1) * OrderMoney::minor(ltrim($value, '-'), $precision);
        $total = $minor($left) + $minor($right);

        return ($total < 0 ? '-' : '').OrderMoney::decimal(abs($total), $precision);
    }
}
