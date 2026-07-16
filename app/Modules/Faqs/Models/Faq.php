<?php

namespace App\Modules\Faqs\Models;

use App\Modules\Faqs\Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
        'sort_order',
        'active',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): FaqFactory
    {
        return FaqFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
