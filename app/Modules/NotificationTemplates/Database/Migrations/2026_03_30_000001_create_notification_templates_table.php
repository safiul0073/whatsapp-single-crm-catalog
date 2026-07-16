<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Email channel
            $table->string('email_subject')->nullable();
            $table->longText('email_body')->nullable();

            // SMS channel
            $table->text('sms_body')->nullable();

            // In-App channel (SystemNotifications)
            $table->string('in_app_title')->nullable();
            $table->text('in_app_body')->nullable();

            // Push channel (shared for web push + mobile push)
            $table->string('push_title')->nullable();
            $table->text('push_body')->nullable();

            // Configuration
            $table->json('channels')->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
