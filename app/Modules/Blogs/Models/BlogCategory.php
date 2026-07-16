<?php

namespace App\Modules\Blogs\Models;

use App\Modules\Blogs\Database\Factories\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): BlogCategoryFactory
    {
        return BlogCategoryFactory::new();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Blog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
