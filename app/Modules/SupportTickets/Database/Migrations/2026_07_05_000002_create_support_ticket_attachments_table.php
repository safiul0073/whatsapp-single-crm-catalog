<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('reply_id')->nullable()->constrained('support_ticket_replies')->cascadeOnDelete();
            $table->string('uploaded_by_type'); // user | admin
            $table->unsignedBigInteger('uploaded_by_id');
            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['uploaded_by_type', 'uploaded_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
    }
};
