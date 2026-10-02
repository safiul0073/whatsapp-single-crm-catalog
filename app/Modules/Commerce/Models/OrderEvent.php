<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    protected $table = 'commerce_order_events';

    protected $fillable = ['notification_attempts', 'order_id', 'key', 'label', 'occurred_at', 'customer_notified_at', 'whatsapp_notified_at', 'staff_notified_at'];

    protected function casts(): array
    {
        return ['notification_attempts' => 'integer', 'whatsapp_notified_at' => 'datetime', 'occurred_at' => 'datetime', 'customer_notified_at' => 'datetime', 'staff_notified_at' => 'datetime'];
    }
}
