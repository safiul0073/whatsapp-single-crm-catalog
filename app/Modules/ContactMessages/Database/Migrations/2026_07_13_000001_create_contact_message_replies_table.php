<?php

use App\Models\Admin;
use App\Modules\ContactMessages\Models\ContactMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_message_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ContactMessage::class)->constrained('contact_messages')->cascadeOnDelete();
            $table->foreignIdFor(Admin::class)->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('notification_log_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->string('source', 32);
            $table->string('template_slug')->nullable();
            $table->string('recipient_email');
            $table->string('subject');
            $table->longText('body');
            $table->json('template_variables')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();

            $table->index(['contact_message_id', 'created_at']);
            $table->index('template_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_replies');
    }
};
