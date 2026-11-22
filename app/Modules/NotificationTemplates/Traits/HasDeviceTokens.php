<?php

namespace App\Modules\NotificationTemplates\Traits;

use App\Modules\NotificationTemplates\Models\DeviceToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDeviceTokens
{
    /**
     * Get all device tokens for this model.
     */
    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'tokenable');
    }

    /**
     * Route notifications for FCM (Firebase Cloud Messaging).
     *
     * @return array<string>
     */
    public function routeNotificationForFcm(mixed $notification = null): array
    {
        $workspaceId = $notification->workspaceId ?? null;

        return $this->deviceTokens()
            ->when($workspaceId, fn ($query) => $query->where(fn ($scoped) => $scoped->where('workspace_id', $workspaceId)->orWhereNull('workspace_id')))
            ->pluck('token')
            ->toArray();
    }

    /**
     * Register a new device token.
     */
    public function addDeviceToken(string $token, string $platform = 'web', ?int $workspaceId = null): DeviceToken
    {
        return $this->deviceTokens()->updateOrCreate(
            ['token' => $token],
            ['platform' => $platform, 'workspace_id' => $workspaceId]
        );
    }

    /**
     * Remove a device token.
     */
    public function removeDeviceToken(string $token): void
    {
        $this->deviceTokens()->where('token', $token)->delete();
    }
}
