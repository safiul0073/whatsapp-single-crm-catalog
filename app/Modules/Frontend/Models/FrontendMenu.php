<?php

namespace App\Modules\Frontend\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FrontendMenu extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $menu) {
            if (! $menu->slug) {
                $menu->slug = Str::slug($menu->name);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(FrontendMenuItem::class)->orderBy('sort_order');
    }

    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
