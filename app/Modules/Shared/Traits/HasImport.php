<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Provides CSV import functionality for service classes.
 *
 * Usage:
 *   class ProductsService
 *   {
 *       use HasCrudOperations, HasImport;
 *
 *       protected array $importable = [
 *           'Name' => 'name',
 *           'Status' => 'is_active',
 *       ];
 *
 *       protected array $importRules = [
 *           'name' => ['required', 'string', 'max:255'],
 *           'is_active' => ['nullable', 'boolean'],
 *       ];
 *   }
 *
 * The $importable array maps CSV headers to database columns.
 * The $importRules array defines validation rules per database column.
 */
trait HasImport
{
    /**
     * CSV header to database column mapping as ['CSV Header' => 'db_column'].
     * Override this in the service class.
     *
     * protected array $importable = [];
     */

    /**
     * Validation rules per database column.
     * Override this in the service class.
     *
     * protected array $importRules = [];
     */

    /**
     * Preview the first N rows of a CSV file.
     *
     * @return array{headers: array, rows: array, total: int}
     */
    public function previewCsv(UploadedFile $file, int $limit = 5): array
    {
        $csv = $this->openCsv($file);

        // Read headers from first row
        $headers = $csv->current();
        if ($headers === false || $headers === [null]) {
            return ['headers' => [], 'rows' => [], 'total' => 0];
        }

        // Clean BOM from first header if present
        $headers = $this->cleanBom($headers);

        $rows = [];
        $total = 0;
        $csv->next();

        while ($csv->valid()) {
            $row = $csv->current();
            $total++;

            if (count($rows) < $limit && $row !== [null]) {
                $rows[] = $row;
            }

            $csv->next();
        }

        // Get the importable mapping for column suggestions
        $importable = $this->importable ?? [];
        $dbColumns = array_values($importable);
        $headerMap = array_flip($importable); // db_column => CSV Header

        // Build suggested mapping: for each CSV header, suggest a db_column if it matches
        $suggestedMap = [];
        foreach ($headers as $header) {
            $trimmed = trim($header);
            if (isset($importable[$trimmed])) {
                $suggestedMap[] = $importable[$trimmed];
            } else {
                // Try case-insensitive match
                $found = null;
                foreach ($importable as $csvHeader => $dbColumn) {
                    if (strcasecmp(trim($csvHeader), $trimmed) === 0) {
                        $found = $dbColumn;
                        break;
                    }
                }
                $suggestedMap[] = $found;
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total' => $total,
            'db_columns' => $dbColumns,
            'suggested_map' => $suggestedMap,
        ];
    }

    /**
     * Import records from a CSV file.
     *
     * @param  array|null  $columnMap  Optional override: [csv_index => db_column]
     * @return array{success: int, failed: int, errors: array}
     */
    public function importCsv(UploadedFile $file, ?array $columnMap = null): array
    {
        $csv = $this->openCsv($file);

        // Read headers
        $headers = $csv->current();
        if ($headers === false || $headers === [null]) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Row 1' => ['CSV file is empty or has no headers.']]];
        }

        $headers = $this->cleanBom($headers);

        // Build the column mapping
        $mapping = $this->resolveColumnMap($headers, $columnMap);

        if (empty($mapping)) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Row 1' => ['No valid column mappings found. Please map at least one column.']]];
        }

        $importRules = $this->importRules ?? [];
        $success = 0;
        $failed = 0;
        $errors = [];
        $rowNumber = 1; // Start at 1 because header is row 0

        $csv->next();

        while ($csv->valid()) {
            $row = $csv->current();
            $rowNumber++;

            // Skip completely empty rows
            if ($row === [null] || $this->isEmptyRow($row)) {
                $csv->next();

                continue;
            }

            // Map CSV values to database columns
            $data = [];
            foreach ($mapping as $csvIndex => $dbColumn) {
                $data[$dbColumn] = isset($row[$csvIndex]) ? trim($row[$csvIndex]) : null;
            }

            // Transform values before validation
            $data = $this->transformImportRow($data);

            // Validate the row
            if (! empty($importRules)) {
                $validator = Validator::make($data, $importRules);

                if ($validator->fails()) {
                    $failed++;
                    $errors["Row {$rowNumber}"] = $validator->errors()->all();
                    $csv->next();

                    continue;
                }
            }

            // Create the record
            try {
                ($this->model)::create($data);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors["Row {$rowNumber}"] = [$e->getMessage()];
            }

            $csv->next();
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Open a CSV file using SplFileObject.
     */
    protected function openCsv(UploadedFile $file): \SplFileObject
    {
        $csv = new \SplFileObject($file->getRealPath());
        $csv->setFlags(
            \SplFileObject::READ_CSV
            | \SplFileObject::READ_AHEAD
            | \SplFileObject::SKIP_EMPTY
            | \SplFileObject::DROP_NEW_LINE
        );

        return $csv;
    }

    /**
     * Clean UTF-8 BOM from the first header value.
     */
    protected function cleanBom(array $headers): array
    {
        if (! empty($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        return array_map('trim', $headers);
    }

    /**
     * Resolve the column mapping from CSV indices to database columns.
     *
     * @return array<int, string> [csv_index => db_column]
     */
    protected function resolveColumnMap(array $headers, ?array $columnMap): array
    {
        // If explicit column map provided, use it (already csv_index => db_column)
        if ($columnMap !== null) {
            $resolved = [];
            foreach ($columnMap as $csvIndex => $dbColumn) {
                if (! empty($dbColumn) && $dbColumn !== '' && $dbColumn !== 'skip') {
                    $resolved[(int) $csvIndex] = $dbColumn;
                }
            }

            return $resolved;
        }

        // Otherwise, auto-map from $importable
        $importable = $this->importable ?? [];
        $mapping = [];

        foreach ($headers as $index => $header) {
            $trimmed = trim($header);

            // Direct match
            if (isset($importable[$trimmed])) {
                $mapping[$index] = $importable[$trimmed];

                continue;
            }

            // Case-insensitive match
            foreach ($importable as $csvHeader => $dbColumn) {
                if (strcasecmp(trim($csvHeader), $trimmed) === 0) {
                    $mapping[$index] = $dbColumn;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Check if a CSV row is effectively empty.
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Transform a mapped row before validation and creation.
     * Override this method to cast values, set defaults, etc.
     *
     * Example:
     *   protected function transformImportRow(array $data): array
     *   {
     *       if (isset($data['is_active'])) {
     *           $data['is_active'] = in_array(strtolower($data['is_active']), ['yes', '1', 'true', 'active']);
     *       }
     *       return $data;
     *   }
     */
    protected function transformImportRow(array $data): array
    {
        return $data;
    }
}
