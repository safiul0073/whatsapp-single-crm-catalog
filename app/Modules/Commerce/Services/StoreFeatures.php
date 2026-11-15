<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Workspaces\Models\Workspace;
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

    /**
     * The business type the owner picked for this workspace (see config/business_types.php).
     */
    public static function type(?int $workspaceId): string
    {
        $default = (string) config('business_types.default');
        if (! $workspaceId) {
            return $default;
        }

        $type = once(fn (): ?string => Workspace::query()->whereKey($workspaceId)->value('settings')['business_type'] ?? null);

        return is_string($type) && array_key_exists($type, (array) config('business_types.types')) ? $type : $default;
    }

    /**
     * Whether the workspace's business type switches on a store-level feature such as "referral".
     */
    public static function typeHas(?int $workspaceId, string $feature): bool
    {
        return in_array($feature, (array) config('business_types.types.'.self::type($workspaceId).'.features'), true);
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
