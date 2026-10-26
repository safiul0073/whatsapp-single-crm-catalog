<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamComment extends Model
{
    protected $table = 'commerce_live_stream_comments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_hidden' => 'boolean',
            'offset_seconds' => 'integer',
        ];
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class, 'live_stream_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
