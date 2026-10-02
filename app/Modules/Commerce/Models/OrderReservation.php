<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReservation extends Model
{
    protected $table = 'commerce_order_reservations';

    protected $fillable = ['generation', 'order_id', 'variant_id', 'quantity', 'state', 'expires_at', 'operation_key'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'quantity' => 'integer'];
    }
}
