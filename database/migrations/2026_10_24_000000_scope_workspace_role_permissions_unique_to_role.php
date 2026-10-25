<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD = 'workspace_role_permissions_unique';

    private const NEW = 'workspace_role_permissions_role_unique';

    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('workspace_role_permissions'))->pluck('name');

        Schema::table('workspace_role_permissions', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains(self::OLD)) {
                $table->dropUnique(self::OLD);
            }

            if (! $indexes->contains(self::NEW)) {
                $table->unique(['workspace_role_id', 'permission_name'], self::NEW);
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspace_role_permissions', function (Blueprint $table): void {
            $table->dropUnique(self::NEW);
        });
    }
};
