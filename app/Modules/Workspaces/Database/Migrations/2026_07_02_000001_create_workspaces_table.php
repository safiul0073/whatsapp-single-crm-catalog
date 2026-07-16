<?php

use App\Models\User;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use App\Modules\Workspaces\Enums\WorkspaceStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class, 'owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('status', WorkspaceStatus::values())->default(WorkspaceStatus::Active->value);
            $table->string('timezone')->default('UTC');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->enum('status', WorkspaceMemberStatus::values())->default(WorkspaceMemberStatus::Active->value);
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role')->default(WorkspaceMemberRole::Staff->value);
            $table->string('token')->unique();
            $table->foreignIdFor(User::class, 'invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
