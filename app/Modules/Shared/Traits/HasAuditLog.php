<?php

namespace App\Modules\Shared\Traits;

use App\Modules\AuditLog\Services\AuditLogService;

trait HasAuditLog
{
    public static function bootHasAuditLog(): void
    {
        $auditEvents = ['created', 'updated', 'deleted'];

        foreach ($auditEvents as $event) {
            static::$event(function ($model) use ($event) {
                // Only log if AuditLogService exists
                if (app()->bound(AuditLogService::class)) {
                    app(AuditLogService::class)->log($model, $event);
                }
            });
        }
    }
}
