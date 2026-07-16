<?php

namespace App\Modules\AuditLog\Services;

use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    protected array $excludeFields = ['password', 'remember_token'];

    public function log(Model $model, string $action): void
    {
        $oldValues = null;
        $newValues = null;

        if ($action === 'updated') {
            $oldValues = collect($model->getOriginal())
                ->except($this->excludeFields)
                ->toArray();
            $newValues = collect($model->getChanges())
                ->except($this->excludeFields)
                ->toArray();
        } elseif ($action === 'created') {
            $newValues = collect($model->getAttributes())
                ->except($this->excludeFields)
                ->toArray();
        } elseif ($action === 'deleted') {
            $oldValues = collect($model->getOriginal())
                ->except($this->excludeFields)
                ->toArray();
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'url' => request()?->fullUrl(),
        ]);
    }

    public function logCustom(string $action, ?array $metadata = null): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => 'system',
            'auditable_id' => null,
            'new_values' => $metadata,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'url' => request()?->fullUrl(),
        ]);
    }
}
