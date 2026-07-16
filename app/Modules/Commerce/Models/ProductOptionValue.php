<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOptionValue extends Model
{
    protected $table = 'commerce_product_option_values';

    protected $fillable = ['workspace_id', 'option_id', 'value', 'position'];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'option_id');
    }
}
