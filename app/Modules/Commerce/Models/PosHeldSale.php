<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/** A parked POS cart the cashier can resume later. */
class PosHeldSale extends Model
{
    protected $table = 'commerce_pos_held_sales';

    protected $fillable = ['workspace_id', 'user_id', 'label', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
