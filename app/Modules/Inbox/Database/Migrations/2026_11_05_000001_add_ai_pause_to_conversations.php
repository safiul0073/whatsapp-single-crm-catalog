<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production change: per-conversation "Human takeover" switch that stops AI chatbot replies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('ai_paused_at')->nullable()->after('assigned_to');
            $table->unsignedBigInteger('ai_paused_by')->nullable()->after('ai_paused_at');
            $table->string('ai_pause_reason', 40)->nullable()->after('ai_paused_by');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['ai_paused_at', 'ai_paused_by', 'ai_pause_reason']);
        });
    }
};
