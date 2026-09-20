<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create workspace_roles table
        Schema::create('workspace_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        // 2. Add workspace_role_id to related tables
        Schema::table('workspace_role_permissions', function (Blueprint $table) {
            $table->foreignId('workspace_role_id')->nullable()->constrained('workspace_roles')->cascadeOnDelete();
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->foreignId('workspace_role_id')->nullable()->constrained('workspace_roles')->cascadeOnDelete();
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->foreignId('workspace_role_id')->nullable()->constrained('workspace_roles')->cascadeOnDelete();
        });

        // 3. Data Migration
        $workspaces = DB::table('workspaces')->get();

        foreach ($workspaces as $workspace) {
            $roles = [
                'administrator' => ['name' => 'Administrator', 'is_system' => true],
                'manager' => ['name' => 'Manager', 'is_system' => true],
                'staff' => ['name' => 'Staff', 'is_system' => true],
            ];

            $roleIds = [];
            foreach ($roles as $key => $roleData) {
                $roleIds[$key] = DB::table('workspace_roles')->insertGetId([
                    'workspace_id' => $workspace->id,
                    'name' => $roleData['name'],
                    'description' => 'System default role',
                    'is_system' => $roleData['is_system'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update workspace_role_permissions
            foreach ($roleIds as $key => $id) {
                DB::table('workspace_role_permissions')
                    ->where('workspace_id', $workspace->id)
                    ->where('role', $key)
                    ->update(['workspace_role_id' => $id]);

                DB::table('workspace_members')
                    ->where('workspace_id', $workspace->id)
                    ->where('role', $key)
                    ->update(['workspace_role_id' => $id]);

                DB::table('workspace_invitations')
                    ->where('workspace_id', $workspace->id)
                    ->where('role', $key)
                    ->update(['workspace_role_id' => $id]);
            }
        }

        // 4. Clean up old columns
        Schema::table('workspace_role_permissions', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_invitations', function (Blueprint $table) {
            $table->string('role')->nullable();
            $table->dropForeign(['workspace_role_id']);
            $table->dropColumn('workspace_role_id');
        });

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->string('role')->nullable();
            $table->dropForeign(['workspace_role_id']);
            $table->dropColumn('workspace_role_id');
        });

        Schema::table('workspace_role_permissions', function (Blueprint $table) {
            $table->string('role')->nullable();
            $table->dropForeign(['workspace_role_id']);
            $table->dropColumn('workspace_role_id');
        });

        Schema::dropIfExists('workspace_roles');
    }
};
