<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('template_slug')->nullable()->index();
            $table->string('channel', 50)->index();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('status', 20)->default('queued');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
