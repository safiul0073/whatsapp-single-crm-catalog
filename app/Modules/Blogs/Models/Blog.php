<?php

namespace App\Modules\Blogs\Models;

use App\Modules\Blogs\Database\Factories\BlogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blog extends Model
{
    /** @use HasFactory<BlogFactory> */
    use HasFactory;

    protected $table = 'blog_posts';

    protected $fillable = [
        'blog_category_id',
        'title',
        'slug',
        'author_name',
        'excerpt',
        'content',
        'featured_image',
        'featured_image_media_id',
        'read_time_minutes',
        'sort_order',
        'active',
        'status',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'read_time_minutes' => 'integer',
            'sort_order' => 'integer',
            'blog_category_id' => 'integer',
            'featured_image_media_id' => 'integer',
            'active' => 'boolean',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): BlogFactory
    {
        return BlogFactory::new();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta_description ?: (string) $this->excerpt;
    }

    public function featuredImageUrl(): ?string
    {
        return media_url($this->featured_image_media_id) ?? ($this->featured_image ? asset($this->featured_image) : null);
    }

    public function safeContentHtml(): string
    {
        $content = trim((string) $this->content);

        if ($content === '') {
            return '';
        }

        if (! str_contains($content, '<')) {
            return collect(preg_split('/\R{2,}/', $content))
                ->map(fn (string $paragraph): string => trim($paragraph))
                ->filter()
                ->map(fn (string $paragraph): string => '<p>'.e($paragraph).'</p>')
                ->implode('');
        }

        $content = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $content) ?? '';
        $content = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]*)/i', '', $content) ?? '';
        $content = preg_replace('/href\s*=\s*([\'"])\s*javascript:.*?\1/i', 'href="#"', $content) ?? '';

        return strip_tags(
            $content,
            '<p><br><strong><b><em><i><u><s><a><ul><ol><li><blockquote><pre><code><h2><h3><h4>'
        );
    }
}
