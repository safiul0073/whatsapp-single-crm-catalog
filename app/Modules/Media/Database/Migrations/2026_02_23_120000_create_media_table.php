<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_name');
            $table->string('original_name');
            $table->string('mime_type');
            $table->string('extension', 20);
            $table->string('type', 20)->index();
            $table->unsignedBigInteger('size');
            $table->string('disk', 20)->default('public');
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
