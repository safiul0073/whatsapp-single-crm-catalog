<?php

namespace App\Modules\Shipping\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingPayment extends Model
{
    public const METHODS = ['bank' => 'Bank transfer', 'cash' => 'Cash', 'mobile' => 'Mobile banking', 'card' => 'Card', 'other' => 'Other'];

    protected $table = 'shipping_payments';

    protected $fillable = [
        'workspace_id',
        'shipping_agent_id',
        'shipping_carrier_account_id',
        'shipping_invoice_id',
        'amount',
        'currency',
        'paid_on',
        'method',
        'reference',
        'receipt_path',
        'receipt_original_name',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'paid_on' => 'date'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ShippingAgent::class, 'shipping_agent_id');
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class, 'shipping_carrier_account_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShippingInvoice::class, 'shipping_invoice_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst($this->method);
    }

    public function providerLabel(): string
    {
        return $this->agent?->name ?? $this->carrierAccount?->name ?? '—';
    }
}
