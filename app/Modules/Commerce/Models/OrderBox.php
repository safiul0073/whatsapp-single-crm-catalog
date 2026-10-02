<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderBox extends Model
{
    protected $table = 'commerce_order_boxes';

    protected $fillable = ['order_id', 'group_id', 'label', 'kind', 'packed_at'];

    protected function casts(): array
    {
        return ['packed_at' => 'datetime'];
    }

    public function contents(): HasMany
    {
        return $this->hasMany(OrderBoxItem::class, 'box_id');
    }
}
