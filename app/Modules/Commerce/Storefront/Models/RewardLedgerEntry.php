<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardLedgerEntry extends Model
{
    protected $table = 'commerce_reward_ledger';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'balance_after_minor' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
