<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentCarton extends Model
{
    public const PACKAGING_TYPES = ['carton' => 'Carton', 'poly_bag' => 'Poly Bag', 'pallet' => 'Pallet', 'envelope' => 'Envelope', 'other' => 'Other'];

    protected $table = 'shipping_shipment_cartons';

    protected $fillable = [
        'workspace_id',
        'shipping_shipment_id',
        'quantity',
        'actual_weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'packaging_type',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'actual_weight_kg' => 'decimal:3',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipping_shipment_id');
    }
}
