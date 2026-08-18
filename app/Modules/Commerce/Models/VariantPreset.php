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
        'type',
        'values',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
