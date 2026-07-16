<?php

namespace App\Modules\Frontend\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FrontendSection extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'data',
        'description',
        'theme_overrides',
        'preview_image_media_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'theme_overrides' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $section) {
            if (! $section->slug) {
                $section->slug = Str::slug($section->name);
            }
        });
    }

    public function pageSections(): HasMany
    {
        return $this->hasMany(PageSection::class, 'frontend_section_id');
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_sections', 'frontend_section_id', 'page_id')
            ->withPivot('sort_order', 'visibility_rules');
    }
}
