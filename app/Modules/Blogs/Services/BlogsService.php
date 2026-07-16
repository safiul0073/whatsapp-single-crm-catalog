<?php

namespace App\Modules\Blogs\Services;

use App\Modules\Blogs\Models\Blog;
use App\Modules\Shared\Traits\HasCrudOperations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogsService
{
    use HasCrudOperations {
        applyFilters as applyCrudFilters;
    }

    protected string $model = Blog::class;

    /** @var array<string> */
    protected array $searchable = ['title', 'excerpt', 'author_name'];

    /** @var array<string> */
    protected array $filterable = ['active', 'status'];

    /** @var array<string> */
    protected array $sortable = ['title', 'sort_order', 'status', 'published_at', 'created_at'];

    protected string $defaultSortBy = 'sort_order';

    protected string $defaultSortOrder = 'asc';

    public function create(array $data): Model
    {
        return ($this->model)::create($this->payload($data));
    }

    public function update(Model $record, array $data): Model
    {
        $record->update($this->payload($data, $record));

        return $record->fresh();
    }

    public function toggleStatus(Model $record): Model
    {
        $record->update(['active' => ! $record->active]);

        return $record->fresh();
    }

    public function findOrFail(int|string $id): Model
    {
        return Blog::query()
            ->whereKey($id)
            ->orWhere('slug', $id)
            ->firstOrFail();
    }

    public function publicPosts(int $perPage = 9): LengthAwarePaginator
    {
        return Blog::query()
            ->active()
            ->published()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /** @return Collection<int, Blog> */
    public function featuredPosts(int $limit = 3): Collection
    {
        return Blog::query()
            ->active()
            ->published()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function publicPostBySlug(string $slug): Blog
    {
        return Blog::query()
            ->active()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['is_active'])) {
            $query->where('active', $filters['is_active']);
        }

        return $this->applyCrudFilters($query, $filters);
    }

    protected function applyEagerLoads(Builder $query): Builder
    {
        return $query->with('category');
    }

    protected function payload(array $data, ?Model $record = null): array
    {
        $status = $data['status'];
        $publishedAt = $record?->published_at;

        if ($status === 'published' && ! $publishedAt) {
            $publishedAt = now();
        }

        if ($status !== 'published') {
            $publishedAt = null;
        }

        return [
            'title' => $data['title'],
            'blog_category_id' => $data['blog_category_id'] ?? null,
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['title'], $record),
            'author_name' => $data['author_name'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'] ?? null,
            'featured_image' => $data['featured_image'] ?? null,
            'featured_image_media_id' => $data['featured_image_media_id'] ?? null,
            'read_time_minutes' => $data['read_time_minutes'],
            'sort_order' => $data['sort_order'],
            'active' => (bool) ($data['active'] ?? true),
            'status' => $status,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'published_at' => $publishedAt,
        ];
    }

    protected function uniqueSlug(?string $slug, string $title, ?Model $record = null): string
    {
        $baseSlug = Str::slug($slug ?: $title) ?: Str::random(8);
        $candidate = $baseSlug;
        $counter = 2;

        while (
            Blog::query()
                ->where('slug', $candidate)
                ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
                ->exists()
        ) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
