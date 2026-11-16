<?php

namespace App\Modules\Shipping\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingAuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'shipping_audit_entries';

    protected $fillable = ['workspace_id', 'shipping_shipment_id', 'user_id', 'action', 'summary', 'old_values', 'new_values'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipping_shipment_id');
    }
}
