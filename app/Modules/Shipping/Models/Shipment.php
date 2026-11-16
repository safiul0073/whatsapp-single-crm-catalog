<?php

namespace App\Modules\Shipping\Models;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shipment extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'quoted' => 'Quoted',
        'approved' => 'Approved',
        'booked' => 'Booked',
        'in_transit' => 'In Transit',
        'delivered' => 'Delivered',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    public const ADDRESS_TYPES = ['residential' => 'Residential', 'commercial' => 'Commercial'];

    public const PAYMENT_STATUSES = ['unpaid' => 'Unpaid', 'partial' => 'Partially Paid', 'paid' => 'Paid'];

    protected $table = 'shipping_shipments';

    protected $fillable = [
        'workspace_id',
        'order_id',
        'reference',
        'status',
        'recipient_name',
        'phone',
        'country_code',
        'state',
        'city',
        'postal_code',
        'address_line1',
        'address_line2',
        'address_type',
        'address_validated_at',
        'shipping_carrier_account_id',
        'shipping_agent_id',
        'carrier',
        'service',
        'total_sets',
        'total_pieces',
        'currency',
        'estimated_cost',
        'approved_cost',
        'final_cost',
        'billed_amount',
        'billed_currency',
        'billed_exchange_rate',
        'approved_quote_id',
        'approved_by',
        'approved_at',
        'tracking_number',
        'tracking_url',
        'payment_status',
        'paid_at',
        'needs_review',
        'review_reason',
        'reviewed_by',
        'reviewed_at',
        'shipped_at',
        'delivered_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'address_validated_at' => 'datetime',
            'total_sets' => 'integer',
            'total_pieces' => 'integer',
            'estimated_cost' => 'decimal:4',
            'approved_cost' => 'decimal:4',
            'final_cost' => 'decimal:4',
            'billed_amount' => 'decimal:4',
            'billed_exchange_rate' => 'decimal:8',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function cartons(): HasMany
    {
        return $this->hasMany(ShipmentCarton::class, 'shipping_shipment_id')->orderBy('id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(ShippingQuote::class, 'shipping_shipment_id');
    }

    public function approvedQuote(): BelongsTo
    {
        return $this->belongsTo(ShippingQuote::class, 'approved_quote_id');
    }

    public function charges(): MorphMany
    {
        return $this->morphMany(ShippingCharge::class, 'chargeable');
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class, 'shipping_carrier_account_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ShippingAgent::class, 'shipping_agent_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(ShippingAuditEntry::class, 'shipping_shipment_id')->latest('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function carrierLabel(): ?string
    {
        return $this->carrier ? (CarrierAccount::CARRIERS[$this->carrier] ?? strtoupper($this->carrier)) : null;
    }

    /**
     * Internal name of whoever moves the parcel: the direct account or the agent.
     */
    public function providerLabel(): ?string
    {
        if ($this->agent) {
            return $this->agent->name.($this->carrierLabel() ? ' ('.$this->carrierLabel().')' : '');
        }

        return $this->carrierAccount?->name ?? $this->carrierLabel();
    }

    public function hasCompleteAddress(): bool
    {
        foreach (['recipient_name', 'phone', 'country_code', 'city', 'postal_code', 'address_line1'] as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return true;
    }
}
