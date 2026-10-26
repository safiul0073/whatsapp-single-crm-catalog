<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Modules\Commerce\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamProduct extends Model
{
    protected $table = 'commerce_live_stream_products';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
        ];
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class, 'live_stream_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
