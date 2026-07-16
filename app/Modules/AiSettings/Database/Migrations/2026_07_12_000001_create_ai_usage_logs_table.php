<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('feature');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('status', 32)->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->text('request_excerpt')->nullable();
            $table->text('response_excerpt')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['feature', 'created_at']);
            $table->index(['provider', 'created_at']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
