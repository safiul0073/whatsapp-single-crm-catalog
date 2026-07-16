<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');
            $table->string('status')->default('draft');
            $table->json('data');
            $table->text('description')->nullable();
            $table->json('theme_overrides')->nullable();
            $table->unsignedBigInteger('preview_image_media_id')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_sections');
    }
};
