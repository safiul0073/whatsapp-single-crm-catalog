<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontVisitor extends Model
{
    protected $table = 'commerce_storefront_visitors';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(StorefrontPageView::class, 'visitor_id');
    }
}
