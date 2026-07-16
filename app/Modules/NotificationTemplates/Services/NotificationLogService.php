<?php

namespace App\Modules\NotificationTemplates\Services;

use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\Shared\Traits\HasCrudOperations;

class NotificationLogService
{
    use HasCrudOperations;

    protected string $model = NotificationLog::class;

    /** @var array<string> */
    protected array $searchable = ['template_slug', 'channel'];

    /** @var array<string> */
    protected array $filterable = ['status', 'channel'];

    protected string $defaultSortBy = 'created_at';

    protected string $defaultSortOrder = 'desc';

    /**
     * Get notification statistics.
     *
     * @return array{total: int, sent: int, failed: int, queued: int}
     */
    public function getStats(): array
    {
        return [
            'total' => NotificationLog::count(),
            'sent' => NotificationLog::where('status', 'sent')->count(),
            'failed' => NotificationLog::where('status', 'failed')->count(),
            'queued' => NotificationLog::where('status', 'queued')->count(),
        ];
    }
}
