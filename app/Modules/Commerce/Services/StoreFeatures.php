<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use Illuminate\Support\Once;

class StoreFeatures
{
    /**
     * Wholesale is on by default; owners switch it off per store in Storefront settings.
     */
    public static function wholesaleEnabled(?int $workspaceId): bool
    {
        if (! $workspaceId) {
            return true;
        }

        return once(fn (): bool => (bool) (StorefrontSetting::query()
            ->where('workspace_id', $workspaceId)
            ->first()?->config['wholesale_enabled'] ?? true));
    }

    public static function flush(): void
    {
        Once::flush();
    }
}
