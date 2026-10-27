<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductType extends Model
{
    public const LAYOUTS = ['apparel', 'jewelry', 'cosmetics', 'food', 'accessories', 'generic'];

    public const VARIANT_AXES = ['size', 'color', 'shade', 'weight', 'volume', 'material', 'metal', 'pack'];

    protected $table = 'commerce_product_types';

    protected $fillable = ['workspace_id', 'name', 'slug', 'layout', 'variant_axes', 'attributes', 'is_system'];

    protected function casts(): array
    {
        return [
            'variant_axes' => 'array',
            'attributes' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'product_type_id');
    }
}
