<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides CSV export functionality for service classes.
 *
 * Usage:
 *   class ProductsService
 *   {
 *       use HasCrudOperations, HasExport;
 *
 *       protected array $exportable = [
 *           'id' => 'ID',
 *           'name' => 'Name',
 *           'is_active' => 'Status',
 *           'created_at' => 'Created At',
 *       ];
 *   }
 *
 * The trait reuses applySearch, applyFilters, applySort from HasCrudOperations.
 */
trait HasExport
{
    /**
     * Columns to export as ['column' => 'Label'].
     * Override this in the service class.
     *
     * protected array $exportable = [];
     */

    /**
     * Export records as a streamed CSV response.
     *
     * @param  array  $filters  Same filters as listPaginated (search, sort, etc.)
     * @param  array|null  $ids  If provided, only export these record IDs
     */
    public function exportCsv(array $filters = [], ?array $ids = null): StreamedResponse
    {
        $exportable = $this->exportable ?? [];

        if (empty($exportable)) {
            throw new \RuntimeException('No exportable columns defined. Set $exportable on the service class.');
        }

        $query = $this->buildExportQuery($filters, $ids);
        $columns = array_keys($exportable);
        $headers = array_values($exportable);

        $modelBaseName = class_basename($this->model);
        $filename = strtolower($modelBaseName).'_export_'.date('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query, $columns, $headers) {
            $handle = fopen('php://output', 'w');

            // BOM for proper UTF-8 in Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, $headers);

            // Stream records in chunks to avoid memory issues
            $query->chunk(500, function ($records) use ($handle, $columns) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($columns as $column) {
                        $value = $this->getExportValue($record, $column);
                        $row[] = $value;
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build the export query with filters and optional ID filtering.
     */
    protected function buildExportQuery(array $filters, ?array $ids): Builder
    {
        $query = ($this->model)::query();

        if ($ids !== null && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        $query = $this->applySearch($query, $filters);
        $query = $this->applyFilters($query, $filters);
        $query = $this->applySort($query, $filters);
        $query = $this->applyEagerLoads($query);

        return $query;
    }

    /**
     * Get the display value for an export column.
     *
     * Supports dot notation for relationships (e.g. 'category.name').
     * Override this method to customize value formatting.
     */
    protected function getExportValue(mixed $record, string $column): string
    {
        $value = data_get($record, $column);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_null($value)) {
            return '';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }
}
