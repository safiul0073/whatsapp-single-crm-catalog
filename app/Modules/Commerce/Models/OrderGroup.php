<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class OrderGroup extends Model
{
    protected $table = 'commerce_order_groups';

    protected $fillable = ['order_id', 'product_id', 'color_id', 'mode', 'product_name', 'color_name', 'box_count', 'ratio', 'multiplier', 'pieces_per_box', 'quantity'];

    protected function casts(): array
    {
        return ['ratio' => 'array', 'box_count' => 'integer', 'quantity' => 'integer'];
    }
}
