<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Workspace roles that already handle order payments also get the new refund permissions.
 */
return new class extends Migration
{
    private const MAP = [
        'commerce.orders.payments.view' => 'commerce.refunds.view',
        'commerce.orders.payments.manage' => 'commerce.refunds.manage',
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('workspace_role_permissions')) {
            return;
        }

        foreach (self::MAP as $existing => $granted) {
            foreach (DB::table('workspace_role_permissions')->where('permission_name', $existing)->get() as $row) {
                $keys = ['workspace_id' => $row->workspace_id, 'workspace_role_id' => $row->workspace_role_id, 'permission_name' => $granted];
                DB::table('workspace_role_permissions')->updateOrInsert($keys, ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('workspace_role_permissions')) {
            DB::table('workspace_role_permissions')->whereIn('permission_name', array_values(self::MAP))->delete();
        }
    }
};
