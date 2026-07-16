<?php

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('trigger')->nullable();
            $table->json('nodes')->nullable();
            $table->json('edges')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('runs_count')->default(0);
            $table->unsignedInteger('completed_runs_count')->default(0);
            $table->unsignedInteger('failed_runs_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
