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
        return self::flag($workspaceId, 'wholesale_enabled');
    }

    /**
     * Retail is on by default; owners switch it off per store in Storefront settings.
     * Retail and wholesale are never both off: a store with retail off sells wholesale only.
     */
    public static function retailEnabled(?int $workspaceId): bool
    {
        if (! self::wholesaleEnabled($workspaceId)) {
            return true;
        }

        return self::flag($workspaceId, 'retail_enabled');
    }

    public static function flush(): void
    {
        Once::flush();
    }

    private static function flag(?int $workspaceId, string $key): bool
    {
        if (! $workspaceId) {
            return true;
        }

        $config = once(fn (): array => (array) (StorefrontSetting::query()
            ->where('workspace_id', $workspaceId)
            ->first()?->config ?? []));

        return (bool) ($config[$key] ?? true);
    }
}
