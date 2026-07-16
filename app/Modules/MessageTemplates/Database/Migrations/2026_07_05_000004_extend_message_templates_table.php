<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table): void {
            $table->string('subject')->nullable()->after('status');
            $table->text('body')->nullable()->after('subject');
            $table->json('buttons')->nullable()->after('components');
            $table->json('variables')->nullable()->after('buttons');
            $table->text('rejection_reason')->nullable()->after('variables');
            $table->string('provider_template_id')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table): void {
            $table->dropColumn(['subject', 'body', 'buttons', 'variables', 'rejection_reason', 'provider_template_id']);
        });
    }
};
