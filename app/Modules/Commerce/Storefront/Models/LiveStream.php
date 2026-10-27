<?php

namespace App\Modules\Commerce\Storefront\Models;

use App\Models\User;
use App\Modules\Commerce\Models\Product;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveStream extends Model
{
    protected $table = 'commerce_live_streams';

    protected $guarded = ['id'];

    protected $hidden = [
        'facebook_stream_url',
    ];

    protected function casts(): array
    {
        return [
            'broadcast_platforms' => 'array',
            'metadata' => 'array',
            'is_in_slider' => 'boolean',
            'chat_enabled' => 'boolean',
            'video_size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'viewer_count' => 'integer',
            'facebook_viewer_count' => 'integer',
            'peak_viewers' => 'integer',
            'total_views' => 'integer',
            'likes_count' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function pinnedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'pinned_product_id');
    }

    public function slider(): BelongsTo
    {
        return $this->belongsTo(StorefrontSlider::class, 'slider_id');
    }

    public function streamProducts(): HasMany
    {
        return $this->hasMany(LiveStreamProduct::class, 'live_stream_id')->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'commerce_live_stream_products', 'live_stream_id', 'product_id')
            ->withPivot(['sort_order', 'is_pinned', 'pinned_at'])
            ->withTimestamps()
            ->orderBy('commerce_live_stream_products.sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LiveStreamComment::class, 'live_stream_id');
    }

    public function hasPlatform(string $platform): bool
    {
        $platforms = $this->broadcast_platforms ?? ['storefront'];

        return in_array($platform, $platforms, true);
    }

    public function videoUrl(): ?string
    {
        if (blank($this->video_path)) {
            return null;
        }

        if (str_starts_with($this->video_path, 'http://') || str_starts_with($this->video_path, 'https://')) {
            return $this->video_path;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.ltrim($this->video_path, '/');
    }

    public function thumbnailUrl(): ?string
    {
        if (blank($this->thumbnail_path)) {
            return null;
        }

        if (str_starts_with($this->thumbnail_path, 'http://') || str_starts_with($this->thumbnail_path, 'https://')) {
            return $this->thumbnail_path;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.ltrim($this->thumbnail_path, '/');
    }
}
