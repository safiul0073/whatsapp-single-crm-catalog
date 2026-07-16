<?php

namespace App\Modules\Contacts\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ContactFileReader
{
    public function sheets(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException('File not found: '.$path);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            return ['Sheet1'];
        }

        $spreadsheet = IOFactory::load($path);
        $names = $spreadsheet->getSheetNames();

        $spreadsheet->disconnectWorksheets();

        return $names;
    }

    public function read(string $path, ?string $sheetName = null): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException('File not found: '.$path);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->readCsv($path);
        }

        return $this->readExcel($path, $sheetName ?? '0');
    }

    public function mapRow(array $row, array $headers, array $mapping): array
    {
        $mapped = [
            'name' => null,
            'phone' => null,
            'email' => null,
            'city' => null,
            'country' => null,
            'source' => null,
            'opt_in_status' => null,
            'groups' => [],
            'tags' => [],
            'custom_fields' => [],
        ];

        foreach ($headers as $index => $header) {
            $field = $mapping[$header] ?? null;
            $value = $row[$index] ?? null;

            if ($field === null || $value === null || trim((string) $value) === '') {
                continue;
            }

            $value = trim((string) $value);

            if (in_array($field, ['name', 'phone', 'email', 'city', 'country', 'source', 'opt_in_status'])) {
                $mapped[$field] = $value;
            } elseif (in_array($field, ['group', 'groups'])) {
                $mapped['groups'] = array_merge($mapped['groups'], $this->splitList($value));
            } elseif (in_array($field, ['tag', 'tags'])) {
                $mapped['tags'] = array_merge($mapped['tags'], $this->splitList($value));
            } elseif (str_starts_with((string) $field, 'custom_')) {
                $key = substr((string) $field, 7);
                $mapped['custom_fields'][$key] = $value;
            }
        }

        return $mapped;
    }

    public function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $value) ?: [])));
    }

    protected function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Cannot open file: '.$path);
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn ($cell) => $cell === null ? '' : $cell, $row);
        }

        fclose($handle);

        $headers = count($rows) > 0 ? array_shift($rows) : [];

        return ['headers' => $headers, 'rows' => $rows];
    }

    protected function readExcel(string $path, string $sheetIdentifier): array
    {
        $spreadsheet = IOFactory::load($path);

        if (is_numeric($sheetIdentifier)) {
            $worksheet = $spreadsheet->getSheet((int) $sheetIdentifier);
        } else {
            try {
                $worksheet = $spreadsheet->getSheetByName($sheetIdentifier)
                    ?? $spreadsheet->getActiveSheet();
            } catch (\Throwable) {
                $worksheet = $spreadsheet->getActiveSheet();
            }
        }

        $data = $worksheet->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();

        if (empty($data)) {
            return ['headers' => [], 'rows' => []];
        }

        $all = array_values($data);
        $headers = array_map('trim', array_shift($all));

        $rows = array_map(function ($row) use ($headers) {
            $values = array_values(array_slice($row, 0, count($headers), true));

            return array_map(fn ($cell) => $cell === null ? '' : trim((string) $cell), $values);
        }, $all);

        return ['headers' => $headers, 'rows' => $rows];
    }
}
