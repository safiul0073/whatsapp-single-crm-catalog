<?php

namespace App\Modules\Contacts\Services;

use App\Models\User;
use App\Modules\Contacts\Enums\ContactImportStatus;
use App\Modules\Contacts\Jobs\ProcessContactImportJob;
use App\Modules\Contacts\Models\ContactImport;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
            'default_country' => strtoupper((string) ($data['default_country'] ?? '')) ?: null,
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

            $hasPhone = $this->contacts->firstValidPhone($mapped['phones'], $options['default_country']) !== null;

            if (! $hasPhone && empty($mapped['email'])) {
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

    public function process(int $importId, ?array $columnMapping = null, ?User $user = null): void
    {
        $import = ContactImport::query()
            ->when($user, fn ($query) => $query->where('workspace_id', $this->workspaces->current($user)->id))
            ->findOrFail($importId);
        $updates = ['status' => ContactImportStatus::Processing];

        if ($columnMapping !== null) {
            $updates['column_mapping'] = $columnMapping;
        }

        $import->update($updates);

        ProcessContactImportJob::dispatch($importId);
    }

    public function show(int $importId, ?User $user = null): ContactImport
    {
        return ContactImport::query()
            ->when($user, fn ($query) => $query->where('workspace_id', $this->workspaces->current($user)->id))
            ->findOrFail($importId);
    }

    /**
     * Recognises plain headers plus Google, Outlook and Apple contact exports
     * (e.g. "Phone 1 - Value", "E-mail Address", "Given Name").
     *
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    public function inferMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalized = str($header)->lower()->replace(['-', ' '], '_')->replaceMatches('/_+/', '_')->trim('_')->toString();

            $mapping[$header] = match (true) {
                in_array($normalized, ['name', 'full_name', 'contact_name', 'display_name'], true) => 'name',
                in_array($normalized, ['first_name', 'given_name'], true) => 'first_name',
                in_array($normalized, ['middle_name', 'additional_name'], true) => 'middle_name',
                in_array($normalized, ['last_name', 'family_name', 'surname'], true) => 'last_name',
                in_array($normalized, ['phone', 'phone_number', 'mobile', 'mobile_number', 'mobile_phone', 'whatsapp', 'whatsapp_number', 'primary_phone', 'home_phone', 'business_phone', 'other_phone'], true),
                (bool) preg_match('/^phone_\d+_value$/', $normalized) => 'phone',
                in_array($normalized, ['email', 'email_address', 'e_mail', 'e_mail_address'], true),
                (bool) preg_match('/^e_?mail_\d+_value$/', $normalized) => 'email',
                in_array($normalized, ['city', 'home_city', 'business_city'], true),
                (bool) preg_match('/^address_\d+_city$/', $normalized) => 'city',
                in_array($normalized, ['country', 'home_country_region', 'business_country_region'], true),
                (bool) preg_match('/^address_\d+_country$/', $normalized) => 'country',
                in_array($normalized, ['tag', 'tags', 'labels', 'group_membership', 'categories'], true) => 'tags',
                in_array($normalized, ['group', 'groups', 'segment', 'segments'], true) => 'groups',
                in_array($normalized, ['organization_name', 'company'], true) => 'custom_company',
                $normalized === 'notes' => 'custom_notes',
                default => '',
            };
        }

        return $mapping;
    }

    /**
     * @param  array<string, string>  $mapping
     */
    public function canAutoStart(array $mapping): bool
    {
        return array_intersect(['phone', 'email'], array_values($mapping)) !== [];
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
