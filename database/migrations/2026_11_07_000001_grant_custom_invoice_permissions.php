<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Roles that could already see or change orders get the matching custom-invoice permission.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'commerce.orders.view' => 'commerce.invoices.view',
        'commerce.orders.update' => 'commerce.invoices.manage',
    ];

    public function up(): void
    {
        if (DB::getSchemaBuilder()->hasTable('workspace_role_permissions')) {
            foreach (self::MAP as $existing => $granted) {
                foreach (DB::table('workspace_role_permissions')->where('permission_name', $existing)->get() as $row) {
                    DB::table('workspace_role_permissions')->updateOrInsert(
                        ['workspace_id' => $row->workspace_id, 'workspace_role_id' => $row->workspace_role_id, 'permission_name' => $granted],
                        ['created_at' => now(), 'updated_at' => now()],
                    );
                }
            }
        }

        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        foreach (self::MAP as $existing => $granted) {
            $existingId = DB::table('permissions')->where('name', $existing)->where('guard_name', 'web')->value('id');
            if (! $existingId) {
                continue;
            }
            $grantedId = DB::table('permissions')->where('name', $granted)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $granted, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

            foreach (['role_has_permissions', 'model_has_permissions'] as $pivot) {
                if (! DB::getSchemaBuilder()->hasTable($pivot)) {
                    continue;
                }
                foreach (DB::table($pivot)->where('permission_id', $existingId)->get() as $row) {
                    $values = ['permission_id' => $grantedId] + array_diff_key((array) $row, ['permission_id' => true]);
                    DB::table($pivot)->updateOrInsert($values, $values);
                }
            }
        }
    }

    public function down(): void
    {
        // Granted permissions are left in place; removing them could strip access given by hand.
    }
};
