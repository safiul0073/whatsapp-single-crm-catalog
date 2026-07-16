<?php

namespace App\Services\Table;

class TableConfig
{
    public static function build($query, $columns)
    {
        $sortField = request('sort', 'id');
        $sortDirection = request('direction', 'desc');
        $search = request('search');

        // 1. Handle Search (Basic Example)
        if ($search) {
            $query->where(function ($q) use ($columns, $search) {
                foreach ($columns as $col) {
                    if ($col['searchable'] ?? false) {
                        $q->orWhere($col['field'], 'like', $search.'%'); // Prefix search is faster
                    }
                }
            });
        }

        // 2. Handle Sort
        $query->orderBy($sortField, $sortDirection);

        // 3. Handle Pagination (High Data Optimized)
        // Use cursorPaginate for millions of rows to avoid "OFFSET" slowness
        return $query->cursorPaginate(request('per_page', 15));
    }
}
