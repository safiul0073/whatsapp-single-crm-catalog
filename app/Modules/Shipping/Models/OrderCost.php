<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Commerce\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCost extends Model
{
    protected $table = 'shipping_order_costs';

    protected $fillable = ['workspace_id', 'order_id', 'product_cost', 'other_costs', 'is_complete', 'items'];

    protected function casts(): array
    {
        return [
            'product_cost' => 'decimal:4',
            'other_costs' => 'decimal:4',
            'is_complete' => 'boolean',
            'items' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
