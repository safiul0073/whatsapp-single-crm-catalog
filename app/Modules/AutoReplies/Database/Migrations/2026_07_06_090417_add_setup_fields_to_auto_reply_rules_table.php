<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_reply_rules', function (Blueprint $table): void {
            $table->string('match_type')->default('contains')->after('trigger_value');
            $table->text('reply_text')->nullable()->after('reply_type');
            $table->unsignedSmallInteger('priority')->default(10)->after('reply_payload');
        });
    }

    public function down(): void
    {
        Schema::table('auto_reply_rules', function (Blueprint $table): void {
            $table->dropColumn(['match_type', 'reply_text', 'priority']);
        });
    }
};
