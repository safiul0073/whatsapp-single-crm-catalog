<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashinRequest extends Model
{
    protected $table = 'commerce_cashin_requests';

    public const METHODS = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'bank' => 'Bank Transfer'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'account_details' => 'encrypted', 'processed_at' => 'datetime', 'window_starts_at' => 'datetime', 'window_ends_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
