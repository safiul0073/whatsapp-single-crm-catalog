<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_webhook_events', function (Blueprint $table): void {
            $table->string('payload_hash')->nullable()->unique()->after('provider_event_id');
            $table->timestamp('failed_at')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('channel_webhook_events', function (Blueprint $table): void {
            $table->dropColumn(['payload_hash', 'failed_at']);
        });
    }
};
