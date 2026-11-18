<?php

namespace App\Modules\Shipping\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ShippingQuote extends Model
{
    public const SOURCES = [
        'agent_chart' => 'Agent rate chart',
        'manual_whatsapp' => 'WhatsApp quotation',
        'manual_direct' => 'Direct courier (manual)',
        'api' => 'Courier API',
    ];

    public const STATUSES = ['unverified' => 'Unverified', 'verified' => 'Verified', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $table = 'shipping_quotes';

    protected $fillable = [
        'workspace_id',
        'shipping_shipment_id',
        'shipping_carrier_account_id',
        'shipping_agent_id',
        'carrier',
        'service',
        'service_code',
        'source',
        'billable_weight_kg',
        'base_rate',
        'total',
        'currency',
        'exchange_rate',
        'total_store',
        'transit_days',
        'remote_status',
        'status',
        'quoted_at',
        'valid_until',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'billable_weight_kg' => 'decimal:3',
            'base_rate' => 'decimal:4',
            'total' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'total_store' => 'decimal:4',
            'quoted_at' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipping_shipment_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ShippingAgent::class, 'shipping_agent_id');
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class, 'shipping_carrier_account_id');
    }

    public function charges(): MorphMany
    {
        return $this->morphMany(ShippingCharge::class, 'chargeable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /**
     * Only a verified, unexpired quotation counts as a confirmed price.
     */
    public function isConfirmedPrice(): bool
    {
        return in_array($this->status, ['verified', 'approved'], true) && ! $this->isExpired();
    }

    public function providerLabel(): string
    {
        $carrier = $this->carrier ? (CarrierAccount::CARRIERS[$this->carrier] ?? strtoupper($this->carrier)) : null;
        if ($this->agent) {
            return $this->agent->name.($carrier ? ' ('.$carrier.')' : '');
        }

        return trim(($carrier ?? 'Courier').' Direct');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
