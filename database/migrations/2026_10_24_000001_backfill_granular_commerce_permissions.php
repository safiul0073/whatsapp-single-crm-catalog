<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private const MAP = [
        'commerce.view' => [
            'commerce.orders.view',
            'commerce.orders.summary.view',
            'commerce.orders.customer.view',
            'commerce.orders.payments.view',
            'commerce.products.view',
            'commerce.customers.view',
            'commerce.referrals.view',
        ],
        'commerce.manage' => [
            'commerce.orders.create',
            'commerce.orders.update',
            'commerce.orders.customer.edit',
            'commerce.orders.payments.manage',
            'commerce.orders.settings',
            'commerce.products.manage',
            'commerce.catalog.manage',
            'commerce.pos.use',
            'commerce.customers.manage',
            'commerce.storefront.settings',
            'commerce.storefront.pages',
            'commerce.storefront.marketing',
            'commerce.storefront.domains',
            'commerce.storefront.reviews',
            'commerce.storefront.credentials',
            'commerce.referrals.manage',
            'commerce.pack-templates.manage',
        ],
    ];

    public function up(): void
    {
        $this->replaceWorkspaceRolePermissions();
        $this->replaceSpatieGrants();
    }

    public function down(): void
    {
        // Legacy permissions are intentionally not restored.
    }

    /**
     * Grant the granular permissions to every workspace role holding a legacy one, then drop the legacy rows.
     */
    private function replaceWorkspaceRolePermissions(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('workspace_role_permissions')) {
            return;
        }

        foreach (self::MAP as $legacy => $granular) {
            $rows = DB::table('workspace_role_permissions')->where('permission_name', $legacy)->get();

            foreach ($rows as $row) {
                foreach ($granular as $name) {
                    $keys = ['workspace_id' => $row->workspace_id, 'workspace_role_id' => $row->workspace_role_id, 'permission_name' => $name];
                    DB::table('workspace_role_permissions')->updateOrInsert($keys, ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        }

        DB::table('workspace_role_permissions')->whereIn('permission_name', array_keys(self::MAP))->delete();
    }

    /**
     * Spatie role/user grants of the legacy `web` permissions.
     */
    private function replaceSpatieGrants(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        foreach (self::MAP as $legacy => $granular) {
            $legacyId = DB::table('permissions')->where('name', $legacy)->where('guard_name', 'web')->value('id');

            if (! $legacyId) {
                continue;
            }

            $ids = collect($granular)->map(fn (string $name) => DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id') ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]));

            foreach (['role_has_permissions' => 'role_id', 'model_has_permissions' => 'model_id'] as $pivot => $owner) {
                if (! DB::getSchemaBuilder()->hasTable($pivot)) {
                    continue;
                }

                foreach (DB::table($pivot)->where('permission_id', $legacyId)->get() as $row) {
                    foreach ($ids as $id) {
                        $values = ['permission_id' => $id] + array_diff_key((array) $row, ['permission_id' => true]);
                        DB::table($pivot)->updateOrInsert($values, $values);
                    }
                }

                DB::table($pivot)->where('permission_id', $legacyId)->delete();
            }

            DB::table('permissions')->where('id', $legacyId)->delete();
        }
    }
};
