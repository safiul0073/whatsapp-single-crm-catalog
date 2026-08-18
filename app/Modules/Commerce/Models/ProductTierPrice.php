<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTierPrice extends Model
{
    protected $table = 'commerce_product_tier_prices';

    protected $fillable = [
        'workspace_id',
        'product_id',
        'min_quantity',
        'max_quantity',
        'unit_price',
        'discount_percentage',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
