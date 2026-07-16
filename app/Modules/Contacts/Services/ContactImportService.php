<?php

namespace App\Modules\Contacts\Services;

use App\Models\User;
use App\Modules\Contacts\Enums\ContactImportStatus;
use App\Modules\Contacts\Jobs\ProcessContactImportJob;
use App\Modules\Contacts\Models\ContactImport;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContactImportService
{
    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected ContactService $contacts,
        protected ContactFileReader $reader,
    ) {}

    public function parse(?User $user, array $data): array
    {
        $workspace = $this->workspaces->current($user);
        /** @var UploadedFile $file */
        $file = $data['file'];
        $mapping = $data['column_mapping'] ?? [];
        $sheetName = $data['sheet'] ?? null;
        $options = [
            'update_existing' => $data['update_existing'] ?? true,
            'mark_optin' => $data['mark_optin'] ?? false,
            'sheet' => $sheetName,
        ];

        $path = $file->storeAs(
            'imports/'.$workspace->id,
            time().'_'.$file->getClientOriginalName(),
        );

        $fullPath = Storage::disk('local')->path($path);

        $result = $this->reader->read($fullPath, $sheetName);
        $headers = $result['headers'];
        $rows = $result['rows'];
        $mapping = $mapping ?: $this->inferMapping($headers);

        $preview = [];
        $totalRows = count($rows);
        $validRows = 0;
        $invalidRows = 0;
        $invalidPhoneRows = [];

        foreach (array_slice($rows, 0, 5) as $row) {
            $preview[] = $this->reader->mapRow($row, $headers, $mapping);
        }

        foreach ($rows as $index => $row) {
            $mapped = $this->reader->mapRow($row, $headers, $mapping);

            if (empty($mapped['phone'])) {
                $invalidRows++;
                $invalidPhoneRows[] = $index + 2;

                continue;
            }

            try {
                $this->contacts->normalizePhone($mapped['phone']);
            } catch (ValidationException) {
                $invalidRows++;
                $invalidPhoneRows[] = $index + 2;

                continue;
            }

            $validRows++;
        }

        $import = ContactImport::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user?->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'source' => 'import',
            'total_rows' => $totalRows,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => $invalidRows,
            'column_mapping' => $mapping,
            'options' => $options,
            'status' => ContactImportStatus::Pending,
        ]);

        $sheets = null;
        if ($sheetName === null && strtolower($file->getClientOriginalExtension()) !== 'csv') {
            $sheets = $this->reader->sheets($fullPath);
        }

        return [
            'import' => $import,
            'preview' => $preview,
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'invalid_phone_rows' => $invalidPhoneRows,
            'sheets' => $sheets,
            'columns' => $this->columns($headers, $rows, $mapping),
        ];
    }

    public function process(int $importId, ?array $columnMapping = null): void
    {
        $import = ContactImport::query()->findOrFail($importId);
        $updates = ['status' => ContactImportStatus::Processing];

        if ($columnMapping !== null) {
            $updates['column_mapping'] = $columnMapping;
        }

        $import->update($updates);

        ProcessContactImportJob::dispatch($importId);
    }

    public function show(int $importId): ContactImport
    {
        return ContactImport::query()->findOrFail($importId);
    }

    protected function inferMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalized = str($header)->lower()->replace([' ', '-'], '_')->toString();

            $mapping[$header] = match ($normalized) {
                'name', 'full_name', 'contact_name' => 'name',
                'phone', 'phone_number', 'mobile', 'mobile_number', 'whatsapp', 'whatsapp_number' => 'phone',
                'email', 'email_address' => 'email',
                'city' => 'city',
                'country' => 'country',
                'tag', 'tags' => 'tags',
                'group', 'groups', 'segment', 'segments' => 'groups',
                default => '',
            };
        }

        return $mapping;
    }

    protected function columns(array $headers, array $rows, array $mapping): array
    {
        $firstRow = $rows[0] ?? [];

        return collect($headers)
            ->map(fn (string $header, int $index): array => [
                'name' => $header,
                'sample' => $firstRow[$index] ?? '',
                'map' => $mapping[$header] ?? '',
            ])
            ->values()
            ->all();
    }
}
