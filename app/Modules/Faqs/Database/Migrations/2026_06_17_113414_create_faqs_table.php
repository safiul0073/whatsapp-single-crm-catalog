<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'active']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
