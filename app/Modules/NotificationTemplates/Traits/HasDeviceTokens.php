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
    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()->pluck('token')->toArray();
    }

    /**
     * Register a new device token.
     */
    public function addDeviceToken(string $token, string $platform = 'web'): DeviceToken
    {
        return $this->deviceTokens()->firstOrCreate(
            ['token' => $token],
            ['platform' => $platform]
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
