<?php

namespace App\Modules\NotificationTemplates\Services;

use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\Shared\Traits\HasCrudOperations;

class NotificationTemplateService
{
    use HasCrudOperations;

    protected string $model = NotificationTemplate::class;

    /** @var array<string> */
    protected array $searchable = ['name', 'slug', 'description'];

    /** @var array<string> */
    protected array $filterable = ['is_active'];

    protected string $defaultSortBy = 'name';

    protected string $defaultSortOrder = 'asc';

    /**
     * Find a template by its slug.
     */
    public function findBySlug(string $slug): ?NotificationTemplate
    {
        return NotificationTemplate::where('slug', $slug)->first();
    }

    /**
     * Sync templates from config to database.
     * Creates new templates, preserves edited ones.
     *
     * @return array{created: int, skipped: int}
     */
    public function syncFromConfig(): array
    {
        $created = 0;
        $skipped = 0;

        foreach (config('notification-templates', []) as $slug => $definition) {
            $existing = NotificationTemplate::withTrashed()->where('slug', $slug)->first();

            if ($existing) {
                $skipped++;

                continue;
            }

            NotificationTemplate::create([
                'slug' => $slug,
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'channels' => $definition['channels'] ?? ['email'],
                'variables' => $definition['variables'] ?? [],
                'email_subject' => $definition['defaults']['email_subject'] ?? null,
                'email_body' => $definition['defaults']['email_body'] ?? null,
                'sms_body' => $definition['defaults']['sms_body'] ?? null,
                'in_app_title' => $definition['defaults']['in_app_title'] ?? null,
                'in_app_body' => $definition['defaults']['in_app_body'] ?? null,
                'push_title' => $definition['defaults']['push_title'] ?? null,
                'push_body' => $definition['defaults']['push_body'] ?? null,
                'is_active' => true,
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
