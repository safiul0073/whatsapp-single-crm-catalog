<?php

namespace App\Modules\Frontend\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FrontendMenuItem extends Model
{
    protected $fillable = [
        'frontend_menu_id',
        'parent_id',
        'item_type',
        'label',
        'linkable_type',
        'linkable_id',
        'url',
        'target',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(FrontendMenu::class, 'frontend_menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
