<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class OrderShipment extends Model
{
    protected $table = 'commerce_order_shipments';

    protected $fillable = ['order_id', 'carrier', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at'];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
