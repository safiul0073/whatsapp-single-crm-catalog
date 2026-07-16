<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $table = 'commerce_inventory_movements';

    protected $fillable = ['workspace_id', 'variant_id', 'order_id', 'quantity_delta', 'reason', 'idempotency_key'];
}
