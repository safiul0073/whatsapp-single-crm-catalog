<?php

namespace App\Modules\Shipping\Services;

use App\Models\User;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\InvoiceLine;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingAgent;
use App\Modules\Shipping\Models\ShippingInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Courier and agent invoices: import, matching to shipments by tracking number,
 * and reconciliation of what was billed against the approved cost.
 */
class InvoiceService
{
    public const DISK = 'local';

    public function __construct(
        private InvoiceImportService $importer,
        private ShipmentService $shipments,
        private ShippingAuditService $audit,
    ) {}

    /**
     * @param  array{provider: string, invoice_number: string, invoice_date?: ?string, due_date?: ?string, currency: string, exchange_rate?: string|float|null, stated_total?: string|float|null, notes?: ?string}  $data
     * @return array{invoice: ShippingInvoice, imported: int, skipped: int}
     */
    public function create(int $workspaceId, array $data, ?UploadedFile $file, User $user): array
    {
        $provider = $this->provider($workspaceId, $data['provider']);
        $currency = strtoupper($data['currency']);
        if ($provider['agent'] && $currency !== strtoupper($provider['agent']->currency)) {
            throw ValidationException::withMessages(['currency' => __(':agent bills in :currency. Enter the invoice in that currency.', ['agent' => $provider['agent']->name, 'currency' => $provider['agent']->currency])]);
        }

        $duplicate = ShippingInvoice::query()
            ->where('workspace_id', $workspaceId)
            ->where('invoice_number', $data['invoice_number'])
            ->where('shipping_agent_id', $provider['agent']?->id)
            ->where('shipping_carrier_account_id', $provider['account']?->id)
            ->where('carrier', $provider['carrier'])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['invoice_number' => __('Invoice :number from this provider is already recorded.', ['number' => $data['invoice_number']])]);
        }

        $extension = $file ? strtolower($file->getClientOriginalExtension()) : null;
        $storedPath = $file?->storeAs('shipping-invoices/'.$workspaceId, Str::random(16).'.'.$extension, self::DISK);
        try {
            $parsed = $storedPath && $extension !== 'pdf' ? $this->importer->parse(Storage::disk(self::DISK)->path($storedPath)) : ['rows' => [], 'skipped' => 0];
            if ($storedPath && $extension !== 'pdf' && $parsed['rows'] === []) {
                throw ValidationException::withMessages(['file' => __('No usable invoice lines were found in this file.')]);
            }
        } catch (ValidationException $exception) {
            Storage::disk(self::DISK)->delete((string) $storedPath);

            throw $exception;
        }

        $storeCurrency = $this->storeCurrency($workspaceId);
        $invoice = DB::transaction(function () use ($workspaceId, $data, $provider, $currency, $file, $extension, $storedPath, $parsed, $user, $storeCurrency): ShippingInvoice {
            $invoice = ShippingInvoice::query()->create([
                'workspace_id' => $workspaceId,
                'shipping_agent_id' => $provider['agent']?->id,
                'shipping_carrier_account_id' => $provider['account']?->id,
                'carrier' => $provider['carrier'],
                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'currency' => $currency,
                'exchange_rate' => $currency === $storeCurrency ? '1' : (string) ($data['exchange_rate'] ?? $provider['agent']?->exchange_rate ?? $provider['account']?->exchange_rate ?? '1'),
                'stated_total' => filled($data['stated_total'] ?? null) ? ShippingMoney::round($data['stated_total'], ShippingMoney::precision($currency)) : null,
                'source_type' => $extension === null ? 'manual' : ($extension === 'xls' ? 'xlsx' : $extension),
                'file_path' => $storedPath,
                'original_name' => $file ? mb_substr($file->getClientOriginalName(), 0, 255) : null,
                'uploaded_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($parsed['rows'] as $row) {
                $this->createLine($invoice, $row);
            }
            $this->refreshTotals($invoice);

            return $invoice;
        });

        $this->refreshBalances($invoice);
        $this->audit->record($workspaceId, 'invoice.uploaded', 'Invoice '.$invoice->invoice_number.' recorded for '.$invoice->providerLabel().' ('.count($parsed['rows']).' line(s))', null, null, ['invoice_id' => $invoice->id, 'currency' => $currency], $user->id);

        return ['invoice' => $invoice, 'imported' => count($parsed['rows']), 'skipped' => $parsed['skipped']];
    }

    /**
     * @param  array{tracking_number?: ?string, reference?: ?string, ship_date?: ?string, billed_weight_kg?: string|float|null, base_amount: string|float, charges?: list<array{reason: string, amount: string|float}>}  $data
     */
    public function addLine(ShippingInvoice $invoice, array $data, User $user): InvoiceLine
    {
        $charges = array_values(array_filter($data['charges'] ?? [], fn (array $charge): bool => (float) $charge['amount'] > 0));
        $precision = ShippingMoney::precision($invoice->currency);
        $line = $this->createLine($invoice, $data + [
            'total_amount' => ShippingMoney::sum(array_merge([$data['base_amount']], array_column($charges, 'amount')), $precision),
            'charges' => $charges,
        ]);
        $this->refreshTotals($invoice);
        $invoice->update(['status' => 'draft']);
        $this->refreshBalances($invoice);
        $this->audit->record($invoice->workspace_id, 'invoice.line_added', 'Line added to invoice '.$invoice->invoice_number.': '.($line->tracking_number ?? $line->reference), null, null, ['total' => $line->total_amount], $user->id);

        return $line;
    }

    public function deleteLine(InvoiceLine $line, User $user): void
    {
        $invoice = $line->invoice;
        $shipment = $line->shipment;
        $line->charges()->delete();
        $line->delete();
        if ($shipment) {
            $this->rebill($shipment, $user);
        }
        $this->refreshStatus($invoice);
        $this->refreshBalances($invoice);
        $this->audit->record($invoice->workspace_id, 'invoice.line_deleted', 'Line removed from invoice '.$invoice->invoice_number.': '.($line->tracking_number ?? $line->reference), $shipment, ['total' => $line->total_amount], null, $user->id);
    }

    /**
     * Matches every open line to a shipment and records the billed amounts on the matched shipments.
     *
     * @return array{matched: int, unmatched: int, duplicates: int, over_approved: int}
     */
    public function reconcile(ShippingInvoice $invoice, User $user): array
    {
        $summary = DB::transaction(function () use ($invoice, $user): array {
            $affected = collect();
            $invoice->load('lines');
            foreach ($invoice->lines as $line) {
                if ($line->match_status === 'ignored') {
                    continue;
                }
                $previous = $line->shipping_shipment_id;
                $this->match($invoice, $line);
                foreach (array_filter([$previous, $line->shipping_shipment_id]) as $shipmentId) {
                    $affected->put($shipmentId, true);
                }
            }

            $overApproved = 0;
            foreach (Shipment::query()->whereIn('id', $affected->keys())->get() as $shipment) {
                $this->rebill($shipment, $user);
                $overApproved += $shipment->refresh()->needs_review ? 1 : 0;
            }

            return $this->refreshStatus($invoice) + ['over_approved' => $overApproved];
        });
        $this->refreshBalances($invoice);

        $this->audit->record($invoice->workspace_id, 'invoice.reconciled', 'Invoice '.$invoice->invoice_number.' reconciled: '.$summary['matched'].' matched, '.$summary['unmatched'].' unmatched, '.$summary['duplicates'].' possible duplicate(s), '.$summary['over_approved'].' over the approved cost', null, null, $summary, $user->id);

        return $summary;
    }

    /**
     * Staff link a line the matcher could not place, or set it aside.
     */
    public function resolveLine(InvoiceLine $line, ?Shipment $shipment, bool $ignore, User $user): InvoiceLine
    {
        return DB::transaction(function () use ($line, $shipment, $ignore, $user): InvoiceLine {
            $previous = $line->shipment;
            $line->update([
                'shipping_shipment_id' => $ignore ? null : $shipment?->id,
                'match_status' => $ignore ? 'ignored' : ($shipment ? 'matched' : 'unmatched'),
                'manually_linked' => ! $ignore && $shipment !== null,
                'note' => $ignore ? 'Set aside by '.$user->name : ($shipment ? 'Linked by '.$user->name : null),
            ]);

            foreach (collect([$previous, $shipment])->filter()->unique('id') as $affected) {
                $this->rebill($affected, $user);
            }
            $this->refreshStatus($line->invoice);
            $this->refreshBalances($line->invoice);
            $this->audit->record($line->workspace_id, $ignore ? 'invoice.line_ignored' : 'invoice.line_linked', 'Invoice '.$line->invoice->invoice_number.' line '.($line->tracking_number ?? $line->reference).($ignore ? ' set aside' : ' linked to '.$shipment?->reference), $shipment, null, null, $user->id);

            return $line;
        });
    }

    public function delete(ShippingInvoice $invoice, User $user): void
    {
        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages(['invoice' => __('Remove the payments recorded against this invoice first.')]);
        }

        DB::transaction(function () use ($invoice, $user): void {
            $shipments = Shipment::query()->whereIn('id', $invoice->lines()->whereNotNull('shipping_shipment_id')->pluck('shipping_shipment_id'))->get();
            foreach ($invoice->lines as $line) {
                $line->charges()->delete();
            }
            $invoice->lines()->delete();
            $invoice->delete();
            foreach ($shipments as $shipment) {
                $this->rebill($shipment, $user);
            }
        });

        if ($invoice->file_path) {
            Storage::disk(self::DISK)->delete($invoice->file_path);
        }
        if ($invoice->agent) {
            app(PaymentService::class)->refreshAgentStatus($invoice->agent);
        }
        $this->audit->record($invoice->workspace_id, 'invoice.deleted', 'Invoice '.$invoice->invoice_number.' removed', null, ['invoice_number' => $invoice->invoice_number], null, $user->id);
    }

    /**
     * Shipment payment status follows the invoices that billed it.
     */
    public function syncShipmentPayments(ShippingInvoice $invoice): void
    {
        $shipmentIds = $invoice->lines()->where('match_status', 'matched')->pluck('shipping_shipment_id')->filter()->unique();
        foreach (Shipment::query()->whereIn('id', $shipmentIds)->get() as $shipment) {
            $status = $this->paymentStatusFor($shipment);
            $shipment->update(['payment_status' => $status, 'paid_at' => $status === 'paid' ? ($shipment->paid_at ?? now()) : null]);
        }
    }

    /**
     * Records the total of every matched invoice line on the shipment, by surcharge reason.
     * Over-approved amounts are flagged for the owner by ShipmentService.
     */
    public function rebill(Shipment $shipment, User $user): void
    {
        $lines = InvoiceLine::query()->with(['invoice', 'charges'])->where('shipping_shipment_id', $shipment->id)->where('match_status', 'matched')->get();
        if ($lines->isEmpty()) {
            return;
        }

        $currencies = $lines->map(fn (InvoiceLine $line): string => $line->invoice->currency.'@'.$line->invoice->exchange_rate)->unique();
        $first = $lines->first()->invoice;
        $single = $currencies->count() === 1;
        $currency = $single ? $first->currency : $shipment->currency;
        $precision = ShippingMoney::precision($currency);
        $inCurrency = fn (string|float|null $amount, ShippingInvoice $invoice): string => $single ? (string) $amount : ShippingMoney::convert($amount, $invoice->currency === $shipment->currency ? '1' : $invoice->exchange_rate, $precision);

        $charges = [];
        foreach ($lines as $line) {
            foreach ($line->charges as $charge) {
                $charges[$charge->reason][] = $inCurrency($charge->amount, $line->invoice);
            }
        }

        $billed = ShippingMoney::sum($lines->map(fn (InvoiceLine $line): string => $inCurrency($line->total_amount, $line->invoice)), $precision);
        $paymentStatus = $this->paymentStatusFor($shipment);
        $unchanged = $shipment->billed_amount !== null && $shipment->billed_currency === $currency
            && ShippingMoney::round($shipment->billed_amount, $precision) === $billed;
        if ($unchanged) {
            $shipment->update(['payment_status' => $paymentStatus, 'paid_at' => $paymentStatus === 'paid' ? ($shipment->paid_at ?? now()) : null]);

            return;
        }

        $this->shipments->recordBilling($shipment, [
            'billed_amount' => $billed,
            'billed_currency' => $currency,
            'billed_exchange_rate' => $single ? ($first->currency === $shipment->currency ? '1' : (string) $first->exchange_rate) : '1',
            'payment_status' => $paymentStatus,
            'charges' => collect($charges)->map(fn (array $amounts, string $reason): array => ['reason' => $reason, 'amount' => ShippingMoney::sum($amounts, $precision)])->values()->all(),
        ], $user);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createLine(ShippingInvoice $invoice, array $row): InvoiceLine
    {
        $precision = ShippingMoney::precision($invoice->currency);
        $line = $invoice->lines()->create([
            'workspace_id' => $invoice->workspace_id,
            'tracking_number' => filled($row['tracking_number'] ?? null) ? mb_substr(preg_replace('/\s+/', '', (string) $row['tracking_number']), 0, 80) : null,
            'reference' => filled($row['reference'] ?? null) ? mb_substr(trim((string) $row['reference']), 0, 80) : null,
            'ship_date' => $row['ship_date'] ?? null,
            'billed_weight_kg' => filled($row['billed_weight_kg'] ?? null) ? $row['billed_weight_kg'] : null,
            'base_amount' => ShippingMoney::round($row['base_amount'] ?? 0, $precision),
            'total_amount' => ShippingMoney::round($row['total_amount'] ?? 0, $precision),
            'match_status' => 'unmatched',
        ]);
        foreach ($row['charges'] ?? [] as $charge) {
            $line->charges()->create([
                'workspace_id' => $invoice->workspace_id,
                'reason' => $charge['reason'],
                'label' => $charge['label'] ?? null,
                'amount' => ShippingMoney::round($charge['amount'], $precision),
            ]);
        }

        return $line;
    }

    private function match(ShippingInvoice $invoice, InvoiceLine $line): void
    {
        if ($line->manually_linked && $line->shipping_shipment_id !== null) {
            $line->update(['match_status' => 'matched']);

            return;
        }

        $shipment = $this->findShipment($invoice->workspace_id, $line);
        if (! $shipment) {
            $line->update(['shipping_shipment_id' => null, 'match_status' => 'unmatched', 'note' => __('No shipment with this tracking number or reference.')]);

            return;
        }

        $duplicate = InvoiceLine::query()
            ->where('shipping_shipment_id', $shipment->id)
            ->where('match_status', 'matched')
            ->where('shipping_invoice_id', '!=', $invoice->id)
            ->where('total_amount', $line->total_amount)
            ->whereHas('invoice', fn ($other) => $other
                ->where('shipping_agent_id', $invoice->shipping_agent_id)
                ->where('shipping_carrier_account_id', $invoice->shipping_carrier_account_id)
                ->where('carrier', $invoice->carrier))
            ->with('invoice')
            ->first();
        if ($duplicate) {
            $line->update(['shipping_shipment_id' => $shipment->id, 'match_status' => 'duplicate', 'note' => __('Same amount already billed on invoice :number. Link it again only if this is a separate charge.', ['number' => $duplicate->invoice->invoice_number])]);

            return;
        }

        $line->update(['shipping_shipment_id' => $shipment->id, 'match_status' => 'matched', 'note' => $this->providerNote($invoice, $shipment)]);
    }

    private function findShipment(int $workspaceId, InvoiceLine $line): ?Shipment
    {
        $base = fn () => Shipment::query()->where('workspace_id', $workspaceId)->where('status', '!=', 'cancelled');

        if (filled($line->tracking_number)) {
            $shipment = $base()->where('tracking_number', $line->tracking_number)->first()
                ?? $base()->whereJsonContains('package_tracking_numbers', $line->tracking_number)->first()
                ?? $base()->where('booking_reference', $line->tracking_number)->first();
            if ($shipment) {
                return $shipment;
            }
        }

        if (filled($line->reference)) {
            $shipment = $base()->where('reference', $line->reference)->first();
            if ($shipment) {
                return $shipment;
            }
            $byOrder = $base()->whereHas('order', fn ($order) => $order->where('number', $line->reference))->limit(2)->get();

            return $byOrder->count() === 1 ? $byOrder->first() : null;
        }

        return null;
    }

    private function providerNote(ShippingInvoice $invoice, Shipment $shipment): ?string
    {
        $sameProvider = $invoice->shipping_agent_id !== null
            ? $invoice->shipping_agent_id === $shipment->shipping_agent_id
            : ($invoice->shipping_carrier_account_id !== null
                ? $invoice->shipping_carrier_account_id === $shipment->shipping_carrier_account_id
                : $invoice->carrier === $shipment->carrier && $shipment->shipping_agent_id === null);

        return $sameProvider ? null : __('Billed by a different provider than the one approved for :reference.', ['reference' => $shipment->reference]);
    }

    /**
     * @return array{matched: int, unmatched: int, duplicates: int}
     */
    private function refreshStatus(ShippingInvoice $invoice): array
    {
        $lines = $invoice->lines()->with('shipment')->get();
        $counts = [
            'matched' => $lines->where('match_status', 'matched')->count(),
            'unmatched' => $lines->where('match_status', 'unmatched')->count(),
            'duplicates' => $lines->where('match_status', 'duplicate')->count(),
        ];
        $flagged = $lines->contains(fn (InvoiceLine $line): bool => $line->match_status === 'matched' && ($line->shipment?->needs_review || (! $line->manually_linked && filled($line->note))));
        $this->refreshTotals($invoice);

        $invoice->update([
            'status' => $counts['unmatched'] > 0 || $counts['duplicates'] > 0 || $flagged || $invoice->totalMismatch() || $lines->isEmpty() ? 'needs_review' : 'reconciled',
            'reconciled_at' => now(),
        ]);

        return $counts;
    }

    /**
     * Keeps the invoice's payment status and its agent's balance status in step with the invoice total.
     * Resolved lazily because PaymentService already depends on this service.
     */
    private function refreshBalances(ShippingInvoice $invoice): void
    {
        app(PaymentService::class)->refreshInvoice($invoice->refresh());
    }

    private function refreshTotals(ShippingInvoice $invoice): void
    {
        $invoice->update(['lines_total' => ShippingMoney::sum($invoice->lines()->where('match_status', '!=', 'ignored')->pluck('total_amount'), ShippingMoney::precision($invoice->currency))]);
    }

    private function paymentStatusFor(Shipment $shipment): string
    {
        $statuses = ShippingInvoice::query()
            ->whereHas('lines', fn ($lines) => $lines->where('shipping_shipment_id', $shipment->id)->where('match_status', 'matched'))
            ->pluck('payment_status');

        return match (true) {
            $statuses->isEmpty() => $shipment->payment_status,
            $statuses->every(fn (string $status): bool => $status === 'paid') => 'paid',
            $statuses->contains(fn (string $status): bool => in_array($status, ['paid', 'partial'], true)) => 'partial',
            default => 'unpaid',
        };
    }

    /**
     * @return array{agent: ?ShippingAgent, account: ?CarrierAccount, carrier: ?string}
     */
    public function provider(int $workspaceId, string $value): array
    {
        [$type, $id] = array_pad(explode(':', $value, 2), 2, '');
        $agent = $type === 'agent' ? ShippingAgent::query()->where('workspace_id', $workspaceId)->find((int) $id) : null;
        $account = $type === 'account' ? CarrierAccount::query()->where('workspace_id', $workspaceId)->find((int) $id) : null;
        $carrier = $type === 'carrier' && array_key_exists($id, CarrierAccount::CARRIERS) ? $id : ($agent?->carrier ?? $account?->carrier);

        if (! $agent && ! $account && ! ($type === 'carrier' && $carrier)) {
            throw ValidationException::withMessages(['provider' => __('Choose the agent or courier that sent this invoice.')]);
        }

        return ['agent' => $agent, 'account' => $account, 'carrier' => $carrier];
    }

    /**
     * Shipments a line can be linked to by hand, for the picker on the invoice page.
     *
     * @return Collection<int, Shipment>
     */
    public function linkableShipments(ShippingInvoice $invoice): Collection
    {
        return Shipment::query()
            ->where('workspace_id', $invoice->workspace_id)
            ->whereNotNull('approved_quote_id')
            ->where('status', '!=', 'cancelled')
            ->latest('id')
            ->limit(300)
            ->get(['id', 'reference', 'tracking_number']);
    }

    private function storeCurrency(int $workspaceId): string
    {
        return StoreOrderSetting::forWorkspace($workspaceId)->currency ?: 'USD';
    }
}
