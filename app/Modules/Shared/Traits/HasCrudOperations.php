<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides standard CRUD operations for service classes.
 *
 * Usage:
 *   class ProductsService
 *   {
 *       use HasCrudOperations;
 *
 *       protected string $model = \App\Modules\Products\Models\Product::class;
 *       protected array $searchable = ['name', 'description'];
 *       protected array $filterable = ['is_active', 'status'];
 *   }
 *
 * All methods can be overridden in the service class for custom logic.
 * Services that don't need CRUD (Settings, Dashboard) simply don't use this trait.
 */
trait HasCrudOperations
{
    /**
     * The Eloquent model class (required).
     *
     * protected string $model = Product::class;
     */

    /**
     * Columns to search with LIKE when 'search' filter is provided.
     * Default: ['name']
     *
     * protected array $searchable = ['name', 'description'];
     */

    /**
     * Columns that accept exact-match filters from the filters array.
     * Default: ['is_active']
     *
     * protected array $filterable = ['is_active', 'status'];
     */

    /**
     * Default sort column.
     * Default: 'created_at'
     *
     * protected string $defaultSortBy = 'created_at';
     */

    /**
     * Default sort direction.
     * Default: 'desc'
     *
     * protected string $defaultSortOrder = 'desc';
     */

    /**
     * Default items per page.
     * Default: 15
     *
     * protected int $defaultPerPage = 15;
     */

    /**
     * Get a paginated list with search, filters, and sorting.
     *
     * Filters array supports:
     *   - 'search'     => string  (searches across $searchable columns)
     *   - 'sort_by'    => string  (column name)
     *   - 'sort_order' => string  ('asc' or 'desc')
     *   - Any key matching $filterable => exact match filter
     */
    public function listPaginated(array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $query = ($this->model)::query();

        $query = $this->applySearch($query, $filters);
        $query = $this->applyFilters($query, $filters);
        $query = $this->applySort($query, $filters);
        $query = $this->applyEagerLoads($query);

        return $query->paginate($perPage ?? $this->defaultPerPage ?? 15);
    }

    /**
     * Find a record by ID or throw 404.
     */
    public function findOrFail(int|string $id): Model
    {
        return ($this->model)::findOrFail($id);
    }

    /**
     * Create a new record.
     */
    public function create(array $data): Model
    {
        return ($this->model)::create($data);
    }

    /**
     * Update an existing record.
     */
    public function update(Model $record, array $data): Model
    {
        $record->update($data);

        return $record->fresh();
    }

    /**
     * Delete a record.
     */
    public function delete(Model $record): bool
    {
        return $record->delete();
    }

    /**
     * Toggle the is_active status of a record.
     */
    public function toggleStatus(Model $record): Model
    {
        $record->update(['is_active' => ! $record->is_active]);

        return $record->fresh();
    }

    /**
     * Apply search across searchable columns.
     * Override this method for custom search logic.
     */
    protected function applySearch(Builder $query, array $filters): Builder
    {
        $search = $filters['search'] ?? null;

        if (empty($search)) {
            return $query;
        }

        $searchable = $this->searchable ?? ['name'];

        return $query->where(function (Builder $q) use ($search, $searchable) {
            foreach ($searchable as $column) {
                $q->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    /**
     * Apply exact-match filters for filterable columns.
     * Override this method for custom filter logic.
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $filterable = $this->filterable ?? ['is_active'];

        foreach ($filterable as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        return $query;
    }

    /**
     * Apply sorting.
     * Override this method for custom sort logic.
     */
    protected function applySort(Builder $query, array $filters): Builder
    {
        $sortBy = $filters['sort_by'] ?? $this->defaultSortBy ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? $this->defaultSortOrder ?? 'desc';

        $sortable = $this->sortable ?? [];
        if ($sortable !== [] && ! in_array($sortBy, $sortable, true)) {
            $sortBy = $this->defaultSortBy ?? 'created_at';
        }

        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Apply eager loading relationships.
     * Override this method to add eager loads: return $query->with('category', 'tags');
     */
    protected function applyEagerLoads(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Bulk delete records by IDs.
     */
    public function bulkDelete(array $ids): int
    {
        return ($this->model)::whereIn('id', $ids)->delete();
    }

    /**
     * Bulk toggle is_active status for records by IDs.
     */
    public function bulkToggleStatus(array $ids): int
    {
        $records = ($this->model)::whereIn('id', $ids)->get();

        foreach ($records as $record) {
            $record->update(['is_active' => ! $record->is_active]);
        }

        return $records->count();
    }
}
