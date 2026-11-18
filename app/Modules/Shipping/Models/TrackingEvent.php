<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    protected $table = 'shipping_tracking_events';

    protected $fillable = [
        'workspace_id',
        'shipping_shipment_id',
        'tracking_number',
        'status',
        'code',
        'description',
        'location',
        'occurred_at',
        'fingerprint',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipping_shipment_id');
    }
}
