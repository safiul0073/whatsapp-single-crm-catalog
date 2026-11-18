<?php

namespace App\Modules\Shipping\Models;

use App\Models\User;
use App\Modules\Shipping\Services\ShippingMoney;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingInvoice extends Model
{
    public const STATUSES = ['draft' => 'Not reconciled', 'reconciled' => 'Reconciled', 'needs_review' => 'Needs review'];

    protected $table = 'shipping_invoices';

    protected $fillable = [
        'workspace_id',
        'shipping_agent_id',
        'shipping_carrier_account_id',
        'carrier',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency',
        'exchange_rate',
        'stated_total',
        'lines_total',
        'paid_amount',
        'status',
        'payment_status',
        'source_type',
        'file_path',
        'original_name',
        'uploaded_by',
        'reconciled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:8',
            'stated_total' => 'decimal:4',
            'lines_total' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'reconciled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'shipping_invoice_id')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ShippingPayment::class, 'shipping_invoice_id')->latest('paid_on');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ShippingAgent::class, 'shipping_agent_id');
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class, 'shipping_carrier_account_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function providerLabel(): string
    {
        $carrier = $this->carrier ? (CarrierAccount::CARRIERS[$this->carrier] ?? strtoupper($this->carrier)) : null;
        if ($this->agent) {
            return $this->agent->name.($carrier ? ' ('.$carrier.')' : '');
        }

        return $this->carrierAccount?->name ?? trim(($carrier ?? 'Courier').' Direct');
    }

    /**
     * The bill as stated on the invoice when entered, otherwise the sum of its lines.
     */
    public function amountDue(): string
    {
        return ShippingMoney::round($this->stated_total ?? $this->lines_total, ShippingMoney::precision($this->currency));
    }

    public function outstanding(): string
    {
        return ShippingMoney::difference($this->amountDue(), $this->paid_amount, ShippingMoney::precision($this->currency));
    }

    /**
     * True when the total printed on the invoice does not equal the lines entered or imported.
     */
    public function totalMismatch(): bool
    {
        $precision = ShippingMoney::precision($this->currency);

        return $this->stated_total !== null && ShippingMoney::difference($this->stated_total, $this->lines_total, $precision) !== ShippingMoney::round(0, $precision);
    }

    public function isOverdue(): bool
    {
        return $this->payment_status !== 'paid' && $this->due_date !== null && $this->due_date->isBefore(today());
    }
}
