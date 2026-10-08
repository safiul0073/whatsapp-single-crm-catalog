<?php

namespace App\Modules\Commerce\Models;

use App\Models\User;
use App\Modules\Commerce\Database\Factories\OrderPaymentFactory;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    /** @use HasFactory<OrderPaymentFactory> */
    use HasFactory;

    protected static function newFactory(): OrderPaymentFactory
    {
        return OrderPaymentFactory::new();
    }

    protected $table = 'commerce_order_payments';

    protected $fillable = ['workspace_id', 'order_id', 'amount', 'currency', 'method', 'reference', 'staff_id', 'recorded_at', 'submission_reference', 'payload_hash', 'tendered_amount', 'change_amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'tendered_amount' => 'decimal:4', 'change_amount' => 'decimal:4', 'recorded_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
