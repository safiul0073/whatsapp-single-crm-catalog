<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InvoiceLine extends Model
{
    public const MATCH_STATUSES = ['matched' => 'Matched', 'unmatched' => 'No matching shipment', 'duplicate' => 'Possible duplicate', 'ignored' => 'Ignored'];

    protected $table = 'shipping_invoice_lines';

    protected $fillable = [
        'workspace_id',
        'shipping_invoice_id',
        'shipping_shipment_id',
        'tracking_number',
        'reference',
        'ship_date',
        'billed_weight_kg',
        'base_amount',
        'total_amount',
        'match_status',
        'manually_linked',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'ship_date' => 'date',
            'billed_weight_kg' => 'decimal:3',
            'base_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'manually_linked' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ShippingInvoice::class, 'shipping_invoice_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipping_shipment_id');
    }

    public function charges(): MorphMany
    {
        return $this->morphMany(ShippingCharge::class, 'chargeable');
    }

    public function matchLabel(): string
    {
        return self::MATCH_STATUSES[$this->match_status] ?? $this->match_status;
    }
}
