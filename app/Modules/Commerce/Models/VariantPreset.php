<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantPreset extends Model
{
    protected $table = 'commerce_variant_presets';

    protected $fillable = [
        'workspace_id',
        'name',
        'sku_suffix',
        'price_delta',
        'weight',
        'weight_unit',
        'type',
        'values',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'weight' => 'decimal:3',
            'values' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function getUsedByProductsCountAttribute(): int
    {
        $suffix = $this->sku_suffix ?: $this->name;
        $name = $this->name;

        return ProductVariant::query()
            ->where('workspace_id', $this->workspace_id)
            ->where(function ($q) use ($name, $suffix): void {
                $q->where('size', $name)
                    ->orWhere('size', $suffix)
                    ->orWhereJsonContains('attributes->size', $name)
                    ->orWhereJsonContains('attributes->size', $suffix)
                    ->orWhere('sku', 'like', '%-'.$suffix);
            })
            ->distinct('product_id')
            ->count('product_id');
    }
}
