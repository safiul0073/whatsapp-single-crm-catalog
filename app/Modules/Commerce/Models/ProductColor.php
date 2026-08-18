<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductColor extends Model
{
    protected $table = 'commerce_product_colors';

    protected $fillable = [
        'workspace_id',
        'product_id',
        'swatch_media_id',
        'name',
        'hex_code',
        'color_family',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
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

    public function swatchMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'swatch_media_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'color_id');
    }

    public function gallery(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'color_id')->with('media')->orderBy('position');
    }

    public function getDisplayNameAttribute(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        if (filled($this->color_family) && filled($this->hex_code)) {
            return "{$this->color_family} ({$this->hex_code})";
        }

        if (filled($this->hex_code)) {
            return strtoupper($this->hex_code);
        }

        return __('Color #:pos', ['pos' => $this->position + 1]);
    }
}
