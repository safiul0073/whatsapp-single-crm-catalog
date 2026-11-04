<?php

namespace App\Modules\Commerce\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontPageView extends Model
{
    public $timestamps = false;

    protected $table = 'commerce_storefront_page_views';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(StorefrontVisitor::class, 'visitor_id');
    }
}
