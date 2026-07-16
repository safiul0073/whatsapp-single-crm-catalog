<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('type', 20);
            $table->string('target');
            $table->string('frequency', 40)->default('hourly');
            $table->string('queue')->default('default');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->string('last_status', 40)->nullable();
            $table->text('last_message')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'frequency']);
            $table->index('queue');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_entries');
    }
};
