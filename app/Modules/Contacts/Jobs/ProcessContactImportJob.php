<?php

namespace App\Modules\Contacts\Jobs;

use App\Modules\Contacts\Enums\ContactImportStatus;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use App\Modules\Contacts\Models\ContactImport;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\Contacts\Services\ContactFileReader;
use App\Modules\Contacts\Services\ContactService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessContactImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importId) {}

    public function handle(ContactService $contacts): void
    {
        $import = ContactImport::query()->findOrFail($this->importId);
        $path = Storage::path($import->file_path);

        if (! file_exists($path)) {
            $import->update([
                'status' => ContactImportStatus::Failed,
                'summary' => ['error' => 'Import file not found.'],
            ]);

            return;
        }

        $reader = app(ContactFileReader::class);
        $result = $reader->read($path, $import->options['sheet'] ?? null);
        $headers = $result['headers'];
        $rows = $result['rows'];
        $mapping = $import->column_mapping;
        $options = $import->options;
        $created = 0;
        $updated = 0;
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $mapped = $reader->mapRow($row, $headers, $mapping);

            if (empty($mapped['phone'])) {
                $skipped++;
                $errors[] = 'Row '.($index + 2).': Missing phone number';

                continue;
            }

            try {
                $normalized = $contacts->normalizePhone($mapped['phone']);
                $exists = Contact::query()
                    ->where('workspace_id', $import->workspace_id)
                    ->where('phone', $normalized)
                    ->exists();

                $contactData = [
                    'phone' => $normalized,
                    'name' => $mapped['name'],
                    'email' => $mapped['email'],
                    'city' => $mapped['city'],
                    'country' => $mapped['country'],
                    'source' => $this->sourceValue($mapped['source'] ?? null),
                    'opt_in_status' => $this->optInStatusValue($mapped['opt_in_status'] ?? null),
                    'custom_fields' => $mapped['custom_fields'],
                    'tag_ids' => $this->tagIds($import->workspace_id, $mapped['tags']),
                    'group_ids' => $this->groupIds($import->workspace_id, $mapped['groups']),
                ];

                if ($options['mark_optin'] ?? false) {
                    $contactData['opt_in_status'] = ContactOptInStatus::Subscribed->value;
                }

                $contacts->upsert($import->workspace_id, $contactData);
                $exists ? $updated++ : $created++;
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Row '.($index + 2).': '.$e->getMessage();
            }
        }

        $import->update([
            'created_rows' => $created,
            'updated_rows' => $updated,
            'imported_rows' => $imported,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
            'status' => ContactImportStatus::Completed,
            'errors' => $errors,
            'summary' => ['errors' => $errors],
            'completed_at' => now(),
        ]);
    }

    protected function tagIds(int $workspaceId, array $names): array
    {
        return array_map(fn (string $name): int => ContactTag::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
            ['name' => $name],
        )->id, array_values(array_unique(array_filter($names))));
    }

    protected function groupIds(int $workspaceId, array $names): array
    {
        return array_map(fn (string $name): int => ContactGroup::query()->firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
            ['name' => $name],
        )->id, array_values(array_unique(array_filter($names))));
    }

    protected function sourceValue(?string $source): string
    {
        return in_array($source, ContactSource::values(), true) ? $source : ContactSource::Import->value;
    }

    protected function optInStatusValue(?string $status): string
    {
        return in_array($status, ContactOptInStatus::values(), true) ? $status : ContactOptInStatus::Unknown->value;
    }
}
