<?php

namespace App\Modules\Frontend\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'status',
        'excerpt',
        'default_layout',
        'theme_overrides',
        'is_system',
        'is_home',
        'meta_title',
        'meta_description',
        'meta_image_media_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'theme_overrides' => 'array',
            'is_system' => 'boolean',
            'is_home' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function pageSections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order');
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(FrontendSection::class, 'page_sections', 'page_id', 'frontend_section_id')
            ->withPivot('sort_order', 'visibility_rules')
            ->orderBy('page_sections.sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
