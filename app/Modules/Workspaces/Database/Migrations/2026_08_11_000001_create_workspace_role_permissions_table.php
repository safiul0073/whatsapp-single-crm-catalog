<?php

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('permission_name');
            $table->timestamps();

            $table->unique(['workspace_id', 'role', 'permission_name'], 'workspace_role_permissions_unique');
            $table->index(['workspace_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_role_permissions');
    }
};
