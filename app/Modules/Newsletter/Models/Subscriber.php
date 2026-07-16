<?php

namespace App\Modules\Newsletter\Models;

use App\Modules\Newsletter\Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory;

    protected static function newFactory(): SubscriberFactory
    {
        return SubscriberFactory::new();
    }

    protected $fillable = [
        'email',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
