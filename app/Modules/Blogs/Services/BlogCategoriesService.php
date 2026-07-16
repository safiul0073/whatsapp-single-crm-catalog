<?php

namespace App\Modules\Blogs\Services;

use App\Modules\Blogs\Models\BlogCategory;
use App\Modules\Shared\Traits\HasCrudOperations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogCategoriesService
{
    use HasCrudOperations {
        applyFilters as applyCrudFilters;
    }

    protected string $model = BlogCategory::class;

    /** @var array<string> */
    protected array $searchable = ['name', 'description'];

    /** @var array<string> */
    protected array $filterable = ['active'];

    /** @var array<string> */
    protected array $sortable = ['name', 'sort_order', 'created_at'];

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

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['is_active'])) {
            $query->where('active', $filters['is_active']);
        }

        return $this->applyCrudFilters($query, $filters);
    }

    protected function applyEagerLoads(Builder $query): Builder
    {
        return $query->withCount('posts');
    }

    protected function payload(array $data, ?Model $record = null): array
    {
        return [
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name'], $record),
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'],
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    protected function uniqueSlug(?string $slug, string $name, ?Model $record = null): string
    {
        $baseSlug = Str::slug($slug ?: $name) ?: Str::random(8);
        $candidate = $baseSlug;
        $counter = 2;

        while (
            BlogCategory::query()
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
